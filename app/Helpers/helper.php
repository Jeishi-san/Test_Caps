<?php

use App\Models\Category;
use App\Models\Folder;
use Illuminate\Support\Str;

if (!function_exists('generateDropdownOptions')) {
    function generateDropdownOptions($parentFolderId = null, $depth = 0)
    {
        return \App\Helpers\FolderHelper::generateDropdownOptions($parentFolderId, $depth);
    }
}

if (!function_exists('generateCategoryTagsDropdownOptions')) {
    function generateCategoryTagsDropdownOptions($parentFolderId = null, $depth = 0)
    {
        $folders = Folder::where('parent_id', $parentFolderId)->get();
        $options = '';

        foreach ($folders as $folder) {
            $indentation = str_repeat('--', $depth); // Add indentation for visual hierarchy
            $options .= '<option value="' . $folder->id . '">' . $indentation . $folder->name . '</option>';

            // Recursively generate options for subfolders
            $options .= generateCategoryTagsDropdownOptions($folder->id, $depth + 1);

            // Fetch and append tags for the current category
            foreach ($folder->categories as $category) {
                $options .= '<option value="' . $category->id . '">----' . $category->name . '</option>';

                // Append tags for the current category
                foreach ($category->tags as $tag) {
                    $options .= '<option value="' . $tag->id . '">--------' . $tag->name . '</option>';
                }
            }
        }

        return $options;
    }
}

if (!function_exists('generateSidebarMenu')) {
    function generateSidebarMenu($parentFolderId = null, $depth = 0)
    {
        return \App\Helpers\FolderHelper::generateSidebarMenu($parentFolderId, $depth);
    }
}


if (!function_exists('getImageExtensions')) {
    function getImageExtensions()
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'tif', 'tiff'];
    }
}


if (!function_exists('getVideoExtensions')) {
    function getVideoExtensions()
    {
        return [
            'mp4',
            'avi',
            'wmv',
            'mov',
            'mkv',
            'flv',
            'webm',
            'mpeg',
            'mpg',
            '3gp',
            'ogg'
        ];
    }
}


if (!function_exists('getDueDateInList')) {
    function getDueDateInList()
    {
        return ['Day', 'Month', 'Year'];
    }
}