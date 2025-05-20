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
        try {
            Log::info("Updating stock for product {$odooProductId} to quantity {$quantity}");
            
            // First, get valid stock locations
            $locations = $this->executeKw('stock.location', 'search_read', [
                [['usage', '=', 'internal']], // Only get internal locations, not views
                ['id', 'name', 'usage']
            ]);
            
            if (empty($locations)) {
                Log::error("No valid stock locations found in Odoo");
                return false;
            }
            
            // Use the first valid internal location
            $locationId = $locations[0]['id'];
            Log::info("Using stock location: {$locations[0]['name']} (ID: {$locationId})");
            
            // Check if the inventory module is available
            $inventoryCheck = $this->executeKw('ir.model', 'search_count', [
                [['model', '=', 'stock.inventory']]
            ]);
            
            if ($inventoryCheck > 0) {
                // Using stock.inventory for older Odoo versions
                $inventoryData = [
                    'name' => $reason ?: 'Stock adjustment from web interface',
                    'location_id' => $locationId,
                    'filter' => 'product',
                    'product_id' => intval($odooProductId)
                ];
                
                // Create inventory adjustment
                $inventoryId = $this->executeKw('stock.inventory', 'create', [$inventoryData]);
                
                if (!$inventoryId) {
                    Log::error("Failed to create inventory adjustment for product {$odooProductId}");
                    return false;
                }
                
                // Prepare the inventory line
                $lineData = [
                    'inventory_id' => $inventoryId,
                    'product_id' => intval($odooProductId),
                    'product_qty' => $quantity,
                    'location_id' => $locationId
                ];
                
                // Create inventory line
                $lineId = $this->executeKw('stock.inventory.line', 'create', [$lineData]);
                
                if (!$lineId) {
                    Log::error("Failed to create inventory line for product {$odooProductId}");
                    return false;
                }
                
                // Validate the inventory adjustment
                $validated = $this->executeKw('stock.inventory', 'action_validate', [[$inventoryId]]);
                
                if ($validated) {
                    Log::info("Stock updated successfully for product {$odooProductId}");
                    return true;
                } else {
                    Log::error("Failed to validate inventory adjustment for product {$odooProductId}");
                    return false;
                }
            } else {
                // For newer Odoo versions that use stock.quant instead
                Log::info("Using stock.quant for inventory adjustment");
                
                // Get the stock quant for this product
                $quants = $this->executeKw('stock.quant', 'search_read', [
                    [
                        ['product_id', '=', intval($odooProductId)],
                        ['location_id', '=', $locationId]
                    ],
                    ['id', 'location_id', 'quantity']
                ]);
                
                if (empty($quants)) {
                    // Create a new quant if none exists
                    $quantData = [
                        'product_id' => intval($odooProductId),
                        'location_id' => $locationId,
                        'quantity' => $quantity
                    ];
                    
                    $quantId = $this->executeKw('stock.quant', 'create', [$quantData]);
                    
                    if ($quantId) {
                        Log::info("Created new stock quant for product {$odooProductId}");
                        return true;
                    } else {
                        Log::error("Failed to create stock quant for product {$odooProductId}");
                        return false;
                    }
                } else {
                    // Update existing quant
                    $quant = $quants[0];
                    $result = $this->executeKw('stock.quant', 'write', [
                        [intval($quant['id'])],
                        ['quantity' => $quantity]
                    ]);
                    
                    if ($result) {
                        Log::info("Updated existing stock quant for product {$odooProductId}");
                        return true;
                    } else {
                        Log::error("Failed to update stock quant for product {$odooProductId}");
                        return false;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Exception updating stock for product {$odooProductId}: " . $e->getMessage());
            return false;
        }
    }

    public function createProduct($productData)
    {
        // Prepare the product data for Odoo
        $odooProductData = [
            'name' => $productData['name'],
            'default_code' => $productData['sku'],
            'description' => $productData['description'],
            'list_price' => $productData['price'],
            'standard_price' => $productData['cost'],
            'categ_id' => $productData['odoo_category_id']
        ];

        // Handle image if present
        if (isset($productData['image_path']) && !empty($productData['image_path'])) {
            try {
                // Read the image file
                $imagePath = $productData['image_path'];
                if (Storage::disk('public')->exists($imagePath)) {
                    $imageContents = Storage::disk('public')->get($imagePath);
                    
                    // Convert to base64 for Odoo
                    $odooProductData['image_1920'] = base64_encode($imageContents);
                }
            } catch (\Exception $e) {
                Log::error('Error processing image for Odoo: ' . $e->getMessage());
                // Continue without the image if there's an error
            }
        }

        // Create the product in Odoo
        $productId = $this->executeKw('product.product', 'create', [$odooProductData]);
        
        return $productId;
    }

    public function updateProduct($odooProductId, $productData)
    {
        try {
            Log::info("Updating product in Odoo: ID {$odooProductId}");
            
            // Prepare the product data for Odoo
            $odooProductData = [
                'name' => $productData['name'],
                'default_code' => $productData['sku'],
                'description' => $productData['description'],
                'list_price' => $productData['price'],
                'standard_price' => $productData['cost']
            ];

            // Add category_id if present
            if (isset($productData['category_id'])) {
                // Get the Odoo category ID from the local category
                $category = Category::find($productData['category_id']);
                if ($category && $category->odoo_category_id) {
                    $odooProductData['categ_id'] = intval($category->odoo_category_id);
                    Log::info("Setting category_id to {$category->odoo_category_id} for product {$odooProductId}");
                } else {
                    Log::warning("Could not find Odoo category ID for local category {$productData['category_id']}");
                }
            }

            // Handle image if present
            if (isset($productData['image_1920']) && !empty($productData['image_1920'])) {
                $odooProductData['image_1920'] = $productData['image_1920'];
                Log::info("Updating image for product {$odooProductId}");
            }

            // Update the product in Odoo
            $result = $this->executeKw('product.product', 'write', [
                [intval($odooProductId)],
                $odooProductData
            ]);
            
            if ($result) {
                Log::info("Product {$odooProductId} updated successfully in Odoo");
                
                // Handle quantity update separately
                if (isset($productData['quantity'])) {
                    Log::info("Updating stock quantity to {$productData['quantity']} for product {$odooProductId}");
                    $stockResult = $this->updateStock(
                        $odooProductId, 
                        $productData['quantity'], 
                        'Stock adjustment from web interface'
                    );
                    
                    if (!$stockResult) {
                        Log::error("Failed to update stock quantity for product {$odooProductId}");
                        // Continue anyway since the product update was successful
                    }
                }
                
                return true;
            } else {
                Log::error("Failed to update product {$odooProductId} in Odoo");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception updating product {$odooProductId}: " . $e->getMessage());
            return false;
        }
    }
}
