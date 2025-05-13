<?php

namespace App\Console\Commands;

// app/Console/Commands/CleanOrphanedImages.php
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;

class CleanOrphanedImages extends Command
{
    protected $signature = 'images:clean';
    protected $description = 'Delete orphaned product images';

    public function handle()
    {
        // Get all referenced images from the database
        $usedImages = Product::pluck('image_path')->filter()->toArray();

        // Get all stored images
        $storedImages = Storage::disk('public')->files('products');

        // Delete unreferenced files
        foreach ($storedImages as $image) {
            if (!in_array($image, $usedImages)) {
                Storage::disk('public')->delete($image);
                $this->info("Deleted orphaned image: $image");
            }
        }
    }
}
