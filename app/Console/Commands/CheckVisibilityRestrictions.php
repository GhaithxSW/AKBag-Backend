<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckVisibilityRestrictions extends Command
{
    protected $signature = 'check:visibility';
    protected $description = 'Check for any visibility restrictions affecting albums, images, and categories';

    public function handle()
    {
        $this->info('Checking for visibility restrictions...');
        
        // Check for any date-based restrictions
        $this->checkDateBasedRestrictions();
        
        // Check for any visibility flags
        $this->checkVisibilityFlags();
        
        // Check for any soft deletes
        $this->checkSoftDeletes();
        
        $this->info('\nVisibility check complete.');
        
        return 0;
    }
    
    protected function checkDateBasedRestrictions()
    {
        $this->info("\n=== Date-based Restrictions ===");
        
        // Check albums
        $this->checkDateField('albums', 'publish_at');
        $this->checkDateField('albums', 'published_until');
        
        // Check images
        $this->checkDateField('images', 'publish_at');
        $this->checkDateField('images', 'published_until');
        
        // Check categories
        $this->checkDateField('categories', 'publish_at');
        $this->checkDateField('categories', 'published_until');
    }
    
    protected function checkDateField($table, $field)
    {
        if (!\Schema::hasColumn($table, $field)) {
            $this->line("- Table '{$table}' does not have a '{$field}' column");
            return;
        }
        
        $count = DB::table($table)
            ->whereNotNull($field)
            ->count();
            
        if ($count > 0) {
            $this->warn("- Found {$count} records in '{$table}' with '{$field}' set");
            
            // Get min and max dates
            $minDate = DB::table($table)
                ->whereNotNull($field)
                ->min($field);
                
            $maxDate = DB::table($table)
                ->whereNotNull($field)
                ->max($field);
                
            $this->line("  Date range for '{$field}': {$minDate} to {$maxDate}");
        } else {
            $this->line("- No records in '{$table}' with '{$field}' set");
        }
    }
    
    protected function checkVisibilityFlags()
    {
        $this->info("\n=== Visibility Flags ===");
        
        // Check for common visibility flag names
        $this->checkFlagField('albums', 'is_visible');
        $this->checkFlagField('albums', 'is_published');
        $this->checkFlagField('albums', 'is_active');
        $this->checkFlagField('albums', 'visible');
        $this->checkFlagField('albums', 'published');
        $this->checkFlagField('albums', 'active');
        $this->checkFlagField('albums', 'status');
        
        $this->checkFlagField('images', 'is_visible');
        $this->checkFlagField('images', 'is_published');
        $this->checkFlagField('images', 'is_active');
        $this->checkFlagField('images', 'visible');
        $this->checkFlagField('images', 'published');
        $this->checkFlagField('images', 'active');
        $this->checkFlagField('images', 'status');
        
        $this->checkFlagField('categories', 'is_visible');
        $this->checkFlagField('categories', 'is_published');
        $this->checkFlagField('categories', 'is_active');
        $this->checkFlagField('categories', 'visible');
        $this->checkFlagField('categories', 'published');
        $this->checkFlagField('categories', 'active');
        $this->checkFlagField('categories', 'status');
    }
    
    protected function checkFlagField($table, $field)
    {
        if (!\Schema::hasColumn($table, $field)) {
            return;
        }
        
        $count = DB::table($table)
            ->where($field, false)
            ->orWhere($field, 0)
            ->orWhere($field, 'inactive')
            ->orWhere($field, 'hidden')
            ->orWhere($field, 'draft')
            ->count();
            
        if ($count > 0) {
            $this->warn("- Found {$count} records in '{$table}' with '{$field}' set to a non-visible state");
            
            // Get the distribution of values
            $values = DB::table($table)
                ->select($field, DB::raw('count(*) as count'))
                ->groupBy($field)
                ->get();
                
            $this->line("  Value distribution for '{$field}':");
            foreach ($values as $value) {
                $val = $value->$field === null ? 'NULL' : $value->$field;
                $this->line("    - {$val}: {$value->count}");
            }
        }
    }
    
    protected function checkSoftDeletes()
    {
        $this->info("\n=== Soft Deletes ===");
        
        // Check if models use SoftDeletes
        $this->checkModelSoftDeletes(Album::class);
        $this->checkModelSoftDeletes(Image::class);
        
        // Check for any soft-deleted records
        $this->checkSoftDeletedRecords('albums', 'deleted_at');
        $this->checkSoftDeletedRecords('images', 'deleted_at');
        $this->checkSoftDeletedRecords('categories', 'deleted_at');
    }
    
    protected function checkModelSoftDeletes($modelClass)
    {
        $reflection = new \ReflectionClass($modelClass);
        $traitNames = $reflection->getTraitNames();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', $traitNames)) {
            $this->line("- {$modelClass} uses SoftDeletes");
        } else {
            $this->line("- {$modelClass} does NOT use SoftDeletes");
        }
    }
    
    protected function checkSoftDeletedRecords($table, $field)
    {
        if (!\Schema::hasColumn($table, $field)) {
            $this->line("- Table '{$table}' does not have a '{$field}' column");
            return;
        }
        
        $count = DB::table($table)
            ->whereNotNull($field)
            ->count();
            
        if ($count > 0) {
            $this->warn("- Found {$count} soft-deleted records in '{$table}'");
            
            // Get the oldest and most recent deletions
            $oldest = DB::table($table)
                ->whereNotNull($field)
                ->orderBy($field, 'asc')
                ->first();
                
            $newest = DB::table($table)
                ->whereNotNull($field)
                ->orderBy($field, 'desc')
                ->first();
                
            if ($oldest && $newest) {
                $this->line("  Deletion date range: {$oldest->$field} to {$newest->$field}");
            }
        } else {
            $this->line("- No soft-deleted records in '{$table}'");
        }
    }
}
