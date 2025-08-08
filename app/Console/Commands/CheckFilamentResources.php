<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class CheckFilamentResources extends Command
{
    protected $signature = 'check:filament-resources';
    protected $description = 'Check if all Filament resources are properly registered and accessible';

    public function handle()
    {
        $this->info('Checking Filament resources...');
        
        // Define the paths to check
        $resourcePath = app_path('Filament/Resources');
        $resourceNamespace = 'App\\Filament\\Resources';
        
        // Get all PHP files in the Resources directory
        $files = File::allFiles($resourcePath);
        
        $resourceClasses = [];
        
        foreach ($files as $file) {
            // Convert file path to class name
            $relativePath = str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );
            
            $className = $resourceNamespace . '\\' . $relativePath;
            
            // Check if the class exists and is a Filament resource
            if (class_exists($className) && is_subclass_of($className, '\Filament\Resources\Resource')) {
                $resourceClasses[] = $className;
            }
        }
        
        $this->info("\nFound " . count($resourceClasses) . " Filament resources:");
        
        $tableData = [];
        
        foreach ($resourceClasses as $class) {
            try {
                $reflection = new ReflectionClass($class);
                $model = $class::getModel();
                $modelCount = $model::count();
                $recordCount = $modelCount > 0 ? $modelCount : '0';
                $status = '✅ OK';
            } catch (\Exception $e) {
                $model = 'Error: ' . $e->getMessage();
                $recordCount = 'N/A';
                $status = '❌ Error';
            }
            
            $tableData[] = [
                'Resource' => class_basename($class),
                'Model' => is_string($model) ? $model : $model::class,
                'Records' => $recordCount,
                'Status' => $status,
                'URL' => $class::getUrl('index')
            ];
        }
        
        $this->table(
            ['Resource', 'Model', 'Records', 'Status', 'URL'],
            $tableData
        );
        
        $this->info("\nTo access these resources, visit the URLs above or navigate through the Filament admin panel.");
        
        return 0;
    }
}
