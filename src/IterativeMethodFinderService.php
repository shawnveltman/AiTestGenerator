<?php

namespace Shawnveltman\AiTestGenerator;

use Illuminate\Support\Facades\Log;
use Shawnveltman\LaravelOpenai\ProviderResponseTrait;

class IterativeMethodFinderService
{
    use ProviderResponseTrait;

    public function __construct(
        public ?MethodLocatorService $method_locator_service = null,
        public ?MethodExtractorService $method_extractor_service = null,
    ) {
        $this->method_locator_service ??= new MethodLocatorService();
        $this->method_extractor_service ??= new MethodExtractorService();
    }

    public function handle(string $namespace, array $methods, int $depth = 2)
    {
        $result = $this->recursive_method_finder($namespace, $methods, $depth);
        $combined_results = $this->combine_results($result);

        return $this->combine_truncated_classes($namespace, $methods, $combined_results);
    }

    public function combine_results(array $results): array
    {
        Log::debug('Results structure:', ['results' => json_encode($results, JSON_PRETTY_PRINT)]);

        $combined = [];
        $this->flatten_and_combine($results, $combined);

        Log::debug('Combined results:', ['combined' => json_encode($combined, JSON_PRETTY_PRINT)]);

        return $combined;
    }

    public function flatten_and_combine($item, &$combined, $current_class = null): void
    {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                if (is_string($key) && strpos($key, 'App\\') === 0) {
                    // This is a class name
                    $this->flatten_and_combine($value, $combined, $key);
                } else {
                    $this->flatten_and_combine($value, $combined, $current_class);
                }
            }
        } elseif (is_string($item) && $current_class) {
            if (! isset($combined[$current_class])) {
                $combined[$current_class] = [];
            }

            if (! in_array($item, $combined[$current_class])) {
                $combined[$current_class][] = $item;
            }
        }
    }

    public function recursive_method_finder(string $namespace, array $methods, int $depth): array
    {
        if ($depth <= 0) {
            return [];
        }

        $class_info = $this->method_locator_service->locate_methods($namespace, $methods);

        if (isset($class_info['error'])) {
            return ['error' => $class_info['error']];
        }

        $truncated_class = $this->method_extractor_service->extract_truncated_class($class_info);

        if (empty($truncated_class)) {
            return ['error' => 'Failed to extract truncated class'];
        }

        $prompt = $this->generate_prompt($truncated_class);
        $response = $this->get_response_from_provider(prompt: $prompt, model: 'gpt-4o-mini', json_mode: false);
        $json = $this->extract_and_parse_json($response);

        if (isset($json['error'])) {
            return $json;
        }

        $result = [$json];

        foreach ($json as $class => $class_methods) {
            $sub_result = $this->recursive_method_finder($class, $class_methods, $depth - 1);

            if (! isset($sub_result['error'])) {
                $result[] = $sub_result;
            }
        }

        return $result;
    }

    public function generate_prompt($truncated_class): string
    {
        return <<<EOD
We're working on a Laravel (V11) project using Livewire (V3) and Filament (V3). Your job is to analyze the given code and determine what ADDITIONAL classes and their specific methods you would need to write fully comprehensive tests. Focus ONLY on the code within the methods provided, not on use statements or class properties. We're only concerned with classes that have a namespace starting with App\\.

Guidelines for analysis:
1. Examine both explicit and implicit relationships in the method code.
2. Look for method calls within the provided methods that imply the existence of other classes or methods.
3. Consider Laravel conventions and common patterns to infer potential methods, but only if they're referenced in the provided method code.
4. Pay special attention to trait usage and what methods it might introduce, but only if used within the provided methods.

For relationships and method calls:
- e.g., "\$first_book_title = auth()->user()->books()->first()->get_title()" implies:
  a) A "books" relationship on the User model
  b) A "get_title" method on the Book model
- e.g., "\$user->get_my_book()" implies a "get_my_book" method on the User class

In your analysis:
1. Use <scratchpad></scratchpad> tags to detail your reasoning for each class and method.
2. In <final_output></final_output> tags, provide a JSON string where:
   - Keys are fully qualified class names (e.g., "App\\Models\\User")
   - Values are arrays of method names you've identified or inferred

Remember to include ONLY methods that are directly referenced or implied by the code within the provided methods. Ignore any classes or methods mentioned in use statements or class properties unless they are explicitly used within the method code.

Code to analyze:
```
{$truncated_class}
```
EOD;
    }

    public function extract_and_parse_json($input_string)
    {
        // Extract JSON string from within <final_output> tags
        $pattern = '/<final_output>(.*?)<\/final_output>/s';
        $matches = [];

        if (preg_match($pattern, $input_string, $matches)) {
            $json_string = $matches[1];

            // Decode JSON string to array
            return $this->get_corrected_json_from_response($json_string);
        }

        return ['error' => 'No <final_output> tags found or empty content'];
    }

    public function combine_truncated_classes(string $original_namespace, array $original_methods, array $final_array): string
    {
        $combined_truncated_classes = "// Original Class\n";

        // Add the original class
        $original_class_info = $this->method_locator_service->locate_methods($original_namespace, $original_methods);

        if (! isset($original_class_info['error'])) {
            $original_truncated_class = $this->method_extractor_service->extract_truncated_class($original_class_info);
            $combined_truncated_classes .= $original_truncated_class."\n\n";
        } else {
            $combined_truncated_classes .= "// Error processing original class {$original_namespace}: {$original_class_info['error']}\n\n";
        }

        $combined_truncated_classes .= "// Additional Classes\n";

        // Process the rest of the classes
        foreach ($final_array as $class => $methods) {
            // Skip the original class if it's in the final array
            if ($class === $original_namespace) {
                continue;
            }

            $class_info = $this->method_locator_service->locate_methods($class, $methods);

            if (! isset($class_info['error'])) {
                $truncated_class = $this->method_extractor_service->extract_truncated_class($class_info);
                $combined_truncated_classes .= $truncated_class."\n\n";
            } else {
                $combined_truncated_classes .= "// Error processing class {$class}: {$class_info['error']}\n\n";
            }
        }

        return $combined_truncated_classes;
    }
}
