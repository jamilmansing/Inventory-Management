<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OdooService
{
    protected $url;
    protected $db;
    protected $username;
    protected $password;
    protected $uid;

    public function __construct()
    {
        $this->url = env('ODOO_URL');
        $this->db = env('ODOO_DB');
        $this->username = env('ODOO_USERNAME');
        $this->password = env('ODOO_PASSWORD');
        $this->authenticate();
    }

    private function authenticate()
    {
        try {
            // Disable SSL verification with the verify option set to false
            $response = Http::withOptions([
                'verify' => false,
            ])->post($this->url . '/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'login',
                    'args' => [$this->db, $this->username, $this->password]
                ],
                'id' => rand(0, 1000000)
            ]);

            $result = $response->json();
            
            if (isset($result['result'])) {
                $this->uid = $result['result'];
                return true;
            } else {
                Log::error('Odoo authentication failed: ' . json_encode($result));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Odoo authentication exception: ' . $e->getMessage());
            return false;
        }
    }

    public function executeKw($model, $method, $args = [], $kwargs = [])
    {
        try {
            // Disable SSL verification with the verify option set to false
            $response = Http::withOptions([
                'verify' => false,
            ])->post($this->url . '/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'object',
                    'method' => 'execute_kw',
                    'args' => [
                        $this->db,
                        $this->uid,
                        $this->password,
                        $model,
                        $method,
                        $args,
                        $kwargs
                    ]
                ],
                'id' => rand(0, 1000000)
            ]);

            $result = $response->json();
            
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                Log::error('Odoo API call failed: ' . json_encode($result));
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Odoo API exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getProducts($limit = 100, $offset = 0)
    {
        return $this->executeKw('product.product', 'search_read', [
            [['active', '=', true]],
            ['id', 'name', 'default_code', 'list_price', 'standard_price', 'qty_available', 'categ_id', 'description', 'barcode', 'image_1920']
        ], [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    public function syncProducts()
    {
        $products = $this->getProducts(500, 0);
        if (!$products) return false;

        $usedImagePaths = Product::pluck('image_path')->filter()->toArray();
        $syncedProductIds = [];

        foreach ($products as $odooProduct) {
            try {
                $syncedProductIds[] = $odooProduct['id'];
                $localProduct = Product::where('odoo_product_id', $odooProduct['id'])->first();

                // Always process Odoo data regardless of timestamp
                $imagePath = $this->processOdooImage($odooProduct, $localProduct);
                $usedImagePaths[] = $imagePath;

                Product::updateOrCreate(
                    ['odoo_product_id' => $odooProduct['id']],
                    $this->buildProductData($odooProduct, $imagePath)
                );

            } catch (\Exception $e) {
                Log::error("Sync failed: {$odooProduct['id']} - " . $e->getMessage());
            }
        }

        // Preserve images for non-synced products
        $usedImagePaths = array_merge(
            $usedImagePaths,
            Product::whereNotIn('odoo_product_id', $syncedProductIds)
                ->pluck('image_path')
                ->filter()
                ->toArray()
        );

        $this->deleteUnusedImages(array_unique($usedImagePaths));
        return true;
    }

    private function processOdooImage($odooProduct, $localProduct)
    {
        if (empty($odooProduct['image_1920'])) return null;

        $imageHash = md5($odooProduct['image_1920']);
        $filename = "odoo_product_{$odooProduct['id']}_{$imageHash}.png";
        $fullPath = "products/{$filename}";

        if (Storage::disk('public')->exists($fullPath)) {
            return $fullPath;
        }

        // Delete old images regardless of timestamp
        $this->deleteOldProductImages($odooProduct['id']);

        $imageData = base64_decode($odooProduct['image_1920'], true);
        if ($imageData === false) {
            Log::error("Invalid image data: {$odooProduct['id']}");
            return $localProduct->image_path ?? null; // Retain existing if present
        }

        Storage::disk('public')->put($fullPath, $imageData);
        return $fullPath;
    }

    private function buildProductData($odooProduct, $imagePath)
    {
        return [
            'name' => $odooProduct['name'],
            'sku' => $odooProduct['default_code'] ?? '',
            'description' => $odooProduct['description'] ?? '',
            'price' => $odooProduct['list_price'],
            'cost' => $odooProduct['standard_price'],
            'quantity' => $odooProduct['qty_available'],
            'category_id' => $this->syncCategory(
                $odooProduct['categ_id'][0], 
                $odooProduct['categ_id'][1]
            ),
            'status' => 'active',
            'image_path' => $imagePath,
            'updated_at' => now() // Force timestamp update
        ];
    }
    

    private function syncCategory($odoo_category_id, $category_name)
    {
        $category = Category::firstOrCreate(
            ['odoo_category_id' => $odoo_category_id],
            [
                'name' => $category_name,
                'description' => 'Imported from Odoo'
            ]
        );

        return $category->id;
    }

    public function syncTransactions($days = 30)
    {
        $date = date('Y-m-d', strtotime('-' . $days . ' days'));
        
        // Get stock moves from Odoo
        $stockMoves = $this->executeKw('stock.move', 'search_read', [
            [['date', '>=', $date]],
            ['id', 'product_id', 'product_qty', 'price_unit', 'state', 'reference', 'date', 'origin']
        ]);

        if (!$stockMoves) {
            return false;
        }

        foreach ($stockMoves as $move) {
            if ($move['state'] != 'done') {
                continue;
            }

            $product = Product::where('odoo_product_id', $move['product_id'][0])->first();
            
            if (!$product) {
                continue;
            }

            // Determine transaction type based on reference or other fields
            $type = 'adjustment';
            if (strpos(strtolower($move['reference']), 'purchase') !== false) {
                $type = 'purchase';
            } elseif (strpos(strtolower($move['reference']), 'sale') !== false) {
                $type = 'sale';
            }

            Transaction::updateOrCreate(
                ['odoo_transaction_id' => $move['id']],
                [
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => $move['product_qty'],
                    'unit_price' => $move['price_unit'],
                    'total_price' => $move['price_unit'] * $move['product_qty'],
                    'reference' => $move['reference'],
                    'notes' => $move['origin'] ?? ''
                ]
            );
        }

        return true;
    }

    public function createProduct($productData)
    {
        $odooProductData = [
            'name' => $productData['name'],
            'default_code' => $productData['sku'],
            'description' => $productData['description'],
            'list_price' => $productData['price'],
            'standard_price' => $productData['cost'],
            'type' => 'product',
            'categ_id' => $productData['odoo_category_id'],
            'image_1920' =>$productData['image_path'] ?? null,
            
        ];

        $productId = $this->executeKw('product.product', 'create', [$odooProductData]);
        
        return $productId;
    }

    public function updateProduct($odooProductId, $productData)
    {
        $odooProductData = [
            'name' => $productData['name'],
            'default_code' => $productData['sku'],
            'description' => $productData['description'],
            'list_price' => $productData['price'],
            'standard_price' => $productData['cost'],
            'image_1920' => $productData['image_1920'] ?? null,
        ];

        $result = $this->executeKw('product.product', 'write', [
            [intval($odooProductId)],
            $odooProductData
        ]);
        
        return $result;
    }

    private function deleteOldProductImages($odooProductId){
        $pattern = "products/odoo_product_{$odooProductId}_*.png";
        $files = Storage::disk('public')->files('products');
        
        foreach ($files as $file) {
            if (preg_match("/odoo_product_{$odooProductId}_/", $file)) {
                Storage::disk('public')->delete($file);
            }
        }
    }


    private function deleteUnusedImages(array $usedImagePaths)
    {
        $allImages = Storage::disk('public')->files('products');
        
        foreach ($allImages as $image) {
            if (!in_array($image, $usedImagePaths)) {
                Storage::disk('public')->delete($image);
                Log::info("Deleted orphaned image: $image");
            }
        }
    }

    public function updateStock($odooProductId, $quantity, $reason = '')
    {
        // This is a simplified version. In a real application, you would use
        // Odoo's inventory adjustment functionality
        $inventoryData = [
            'product_id' => intval($odooProductId),
            'product_qty' => $quantity,
            'location_id' => 2, // Assumes stock location ID 2, adjust as needed
            'name' => $reason
        ];

        $result = $this->executeKw('stock.inventory', 'create', [$inventoryData]);
        
        if ($result) {
            $this->executeKw('stock.inventory', 'action_validate', [[$result]]);
            return true;
        }
        
        return false;
    }
}





