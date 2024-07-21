<?php

namespace Shawnveltman\AiTestGenerator;

use Illuminate\Support\Facades\File;

class MethodExtractorService
{
    public function extract_truncated_class(array $class_info): string
    {
        if (! File::exists($class_info['filepath']))
        {
            return '';
        }

        $file_content = File::get($class_info['filepath']);
        $lines        = explode("\n", $file_content);

        // Get content up to class declaration and add opening brace
        $truncated_content = implode("\n", array_slice($lines, 0, $class_info['class_start_line']));
        $truncated_content .= "\n{"; // Add opening brace

        // Add each specified method from the class
        foreach ($class_info['methods'] as $method)
        {
            $method_content = $this->extract_method_content($class_info['filepath'], $method);
            $truncated_content .= "\n" . $method_content;
        }

        // Add methods from traits
        foreach ($class_info['traits'] as $trait_name => $trait_info)
        {
            $truncated_content .= "\n\n    // Methods from trait: {$trait_name}\n";

            foreach ($trait_info['methods'] as $method)
            {
                $method_content = $this->extract_method_content($trait_info['filepath'], $method);
                $truncated_content .= "\n" . $this->indent_method($method_content);
            }
        }

        // Add closing brace for the class
        $truncated_content .= "\n}";

        return $truncated_content;
    }

    private function extract_method_content(string $filepath, array $method): string
    {
        $file_content = File::get($filepath);
        $lines        = explode("\n", $file_content);

        return implode("\n", array_slice($lines, $method['start_line'] - 1, $method['end_line'] - $method['start_line'] + 1));
    }

    private function indent_method(string $method_content): string
    {
        $lines          = explode("\n", $method_content);
        $indented_lines = array_map(function ($line)
        {
            return '    ' . $line; // Add 4 spaces of indentation
        }, $lines);

        return implode("\n", $indented_lines);
    }
}
