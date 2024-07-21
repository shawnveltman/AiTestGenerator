<?php

namespace Shawnveltman\AiTestGenerator;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

class MethodLocatorService
{
    public function locate_methods(string $namespace, array $method_names): array
    {
        try
        {
            $reflection_class = new ReflectionClass($namespace);
            $file_name        = $reflection_class->getFileName();
            $class_start_line = $reflection_class->getStartLine();

            $methods_info = [];
            $traits_info  = [];

            foreach ($method_names as $method_name)
            {
                if (! $reflection_class->hasMethod($method_name))
                {
                    continue; // Skip if method doesn't exist
                }

                $reflection_method = $reflection_class->getMethod($method_name);
                $method_info       = $this->get_method_info($reflection_method);

                // Check if the method is from a trait
                $trait_name = $this->get_trait_name_for_method($reflection_class, $method_name);

                if ($trait_name)
                {
                    if (! isset($traits_info[$trait_name]))
                    {
                        $trait_reflection         = new ReflectionClass($trait_name);
                        $traits_info[$trait_name] = [
                            'filepath' => $trait_reflection->getFileName(),
                            'methods'  => [],
                        ];
                    }
                    $traits_info[$trait_name]['methods'][] = $method_info;
                } else
                {
                    $methods_info[] = $method_info;
                }
            }

            return [
                'filepath'         => $file_name,
                'class_start_line' => $class_start_line,
                'methods'          => $methods_info,
                'traits'           => $traits_info,
            ];
        } catch (ReflectionException $e)
        {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    private function get_method_info(ReflectionMethod $method): array
    {
        return [
            'name'       => $method->getName(),
            'start_line' => $method->getStartLine(),
            'end_line'   => $method->getEndLine(),
        ];
    }

    private function get_trait_name_for_method(ReflectionClass $class, string $method_name): ?string
    {
        $traits = $class->getTraits();

        foreach ($traits as $trait)
        {
            if ($trait->hasMethod($method_name))
            {
                $trait_method = $trait->getMethod($method_name);

                if ($trait_method->getDeclaringClass()->getName() === $trait->getName())
                {
                    return $trait->getName();
                }
            }
        }

        return null;
    }
}
