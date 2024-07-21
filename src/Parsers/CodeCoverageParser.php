<?php

namespace Shawnveltman\AiTestGenerator\Parsers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;

class CodeCoverageParser
{
    public float $coverage_percentage;

    public string $class_name;

    public string $namespace;

    public array $test_suites = [];

    public array $methods_without_coverage = [];

    private int $MINIMUM_COVERAGE_PERCENTAGE = 100;

    public function parse(string $filepath): self
    {
        $coverage_file = Storage::disk('base_path')->get($filepath);

        $dom = new DOMDocument();
        $dom->loadXML($coverage_file);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ns', 'https://schema.phpunit.de/coverage/1.0');

        $this->extract_coverage_percentage($xpath);
        $this->extract_class_info($xpath);
        $this->extract_methods_without_full_coverage($xpath);
        $this->extract_test_suites($xpath);

        return $this;
    }

    private function extract_coverage_percentage(DOMXPath $xpath): void
    {
        $percent_node = $xpath->query('//ns:file/ns:totals/ns:lines/@percent')->item(0);
        $this->coverage_percentage = $percent_node ? (float) $percent_node->nodeValue : 0;
    }

    private function extract_class_info(DOMXPath $xpath): void
    {
        $class_node = $xpath->query('//ns:file/ns:class/@name')->item(0);
        $this->class_name = $class_node ? $class_node->nodeValue : '';

        $namespace_node = $xpath->query('//ns:file/ns:class/ns:namespace/@name')->item(0);
        $this->namespace = $namespace_node ? $namespace_node->nodeValue : '';
    }

    private function extract_methods_without_full_coverage(DOMXPath $xpath): void
    {
        $methods = $xpath->query('//ns:file/ns:class/ns:method');

        foreach ($methods as $method) {
            $coverage = (float) $method->getAttribute('coverage');

            if ($coverage < $this->MINIMUM_COVERAGE_PERCENTAGE) {
                $this->methods_without_coverage[] = $method->getAttribute('name');
            }
        }
    }

    private function extract_test_suites(DOMXPath $xpath): void
    {
        $lines = $xpath->query('//ns:file/ns:coverage/ns:line');

        foreach ($lines as $line) {
            foreach ($line->getElementsByTagName('covered') as $covered) {
                $covered_by = $covered->getAttribute('by');

                if (preg_match('/^(.*)::/', $covered_by, $matches)) {
                    $test_class = str_replace(['P\\', '\\'], ['', '\\'], $matches[1]);

                    if (! in_array($test_class, $this->test_suites, true)) {
                        $this->test_suites[] = $test_class;
                    }
                }
            }
        }
    }

    public function get_useful_information(): array
    {
        return [
            'coverage_percentage' => $this->coverage_percentage,
            'class_name' => $this->class_name,
            'namespace' => $this->namespace,
            'test_suites' => $this->test_suites,
            'methods_without_coverage' => $this->methods_without_coverage,
        ];
    }
}
