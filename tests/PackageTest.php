<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Shawnveltman\AiTestGenerator\AiTestGenerator;
use Shawnveltman\AiTestGenerator\IterativeMethodFinderService;
use Shawnveltman\AiTestGenerator\Parsers\CodeCoverageParser;
use Shawnveltman\AiTestGenerator\Tests\Fixtures\TestClass;

afterEach(function () {
    Mockery::close();
});

// Tests for CodeCoverageParser

it('parses coverage file correctly', function () {
    $parser = new CodeCoverageParser();
    $sample_xml = <<<XML
<?xml version="1.0"?>
<phpunit xmlns="https://schema.phpunit.de/coverage/1.0">
  <file name="LanguageSelectionComponent.php" path="/Livewire">
    <totals>
      <lines total="14" comments="0" code="14" executable="1" executed="1" percent="100.00"/>
    </totals>
    <class name="App\Livewire\LanguageSelectionComponent" start="7" executable="1" executed="1" crap="1">
      <namespace name="App\Livewire"/>
      <method name="render" signature="render()" start="9" end="12" crap="1" executable="1" executed="1" coverage="100"/>
    </class>
    <coverage>
      <line nr="11">
        <covered by="P\Tests\Feature\Livewire\LanguageSelectionComponentTest::__pest_evaluable_it_does_load_for_authenticated_users"/>
      </line>
    </coverage>
  </file>
</phpunit>
XML;

    Storage::fake('base_path');
    Storage::disk('base_path')->put('coverage.xml', $sample_xml);

    $result = $parser->parse('coverage.xml');

    expect($result)->toBeInstanceOf(CodeCoverageParser::class)
        ->and($result->coverage_percentage)->toBe(100.0)
        ->and($result->class_name)->toBe('App\Livewire\LanguageSelectionComponent')
        ->and($result->namespace)->toBe('App\Livewire')
        ->and($result->methods_without_coverage)->toBeEmpty()
        ->and($result->test_suites)->toContain('Tests\Feature\Livewire\LanguageSelectionComponentTest');
});

it('returns useful information', function () {
    $parser = new CodeCoverageParser();
    $parser->coverage_percentage = 80.0;
    $parser->class_name = 'TestClass';
    $parser->namespace = 'TestNamespace';
    $parser->test_suites = ['TestSuite1', 'TestSuite2'];
    $parser->methods_without_coverage = ['method1', 'method2'];

    $info = $parser->get_useful_information();

    expect($info)->toBeArray()
        ->toHaveKeys(['coverage_percentage', 'class_name', 'namespace', 'test_suites', 'methods_without_coverage'])
        ->and($info['coverage_percentage'])->toBe(80.0)
        ->and($info['class_name'])->toBe('TestClass')
        ->and($info['namespace'])->toBe('TestNamespace')
        ->and($info['test_suites'])->toBe(['TestSuite1', 'TestSuite2'])
        ->and($info['methods_without_coverage'])->toBe(['method1', 'method2']);
});

// Tests for AiTestGenerator

it('generates and stores test file', function () {
    // Fake the default storage disk
    Storage::fake('default');

    // Ensure the AiTestGenerator uses the faked storage
    config(['filesystems.default' => 'default']);

    // Fake HTTP responses
    Http::fake([
        '*' => Http::response([
            'content' => [
                ['text' => '<?php // Generated test content'],
            ],
            'stop_reason' => 'stop',
        ]),
    ]);

    // Create an instance of AiTestGenerator
    $generator = new AiTestGenerator();

    // Handle the generation
    $generator->handle('App\\Models\\User', ['test_method']);

    // Check if a file was created in the generated_tests directory
    $files = Storage::files('generated_tests');

    expect($files)->not->toBeEmpty();

    if (! empty($files)) {
        $created_file = $files[0];

        // Verify the content of the generated file
        $file_content = Storage::get($created_file);
        expect($file_content)->toBe('<?php // Generated test content');
    }
});

// Tests for IterativeMethodFinderService

it('combines results correctly', function () {
    $service = new IterativeMethodFinderService();
    $input = [
        'App\\Models\\User' => ['method1', 'method2'],
        'App\\Services\\UserService' => ['serviceMethod'],
        ['App\\Models\\Post' => ['postMethod']],
    ];

    $result = $service->combine_results($input);

    expect($result)->toBeArray()
        ->toHaveKeys(['App\\Models\\User', 'App\\Services\\UserService', 'App\\Models\\Post'])
        ->and($result['App\\Models\\User'])->toBe(['method1', 'method2'])
        ->and($result['App\\Services\\UserService'])->toBe(['serviceMethod'])
        ->and($result['App\\Models\\Post'])->toBe(['postMethod']);
});

it('extracts and parses JSON correctly', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'App\\Models\\User' => ['method1', 'method2'],
                            'App\\Services\\UserService' => ['serviceMethod'],
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $service = new IterativeMethodFinderService();
    $input = '<final_output>{"App\\Models\\User": ["method1", "method2"],"App\\Services\\UserService": ["serviceMethod"]}</final_output>';

    $result = $service->extract_and_parse_json($input);

    expect($result)->toBeArray()
        ->toHaveKeys(['App\\Models\\User', 'App\\Services\\UserService'])
        ->and($result['App\\Models\\User'])->toBe(['method1', 'method2'])
        ->and($result['App\\Services\\UserService'])->toBe(['serviceMethod']);
});

it('generates prompt correctly', function () {
    $service = new IterativeMethodFinderService();
    $truncated_class = "class TestClass {\n    public function testMethod() {}\n}";

    $prompt = $service->generate_prompt($truncated_class);

    expect($prompt)->toBeString()
        ->toContain("We're working on a Laravel (V11) project")
        ->toContain($truncated_class);
});

it('handles recursive method finding', function () {
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => '<final_output>'.json_encode([
                            TestClass::class => ['testMethod'],
                        ]).'</final_output>',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]),
    ]);

    $service = new IterativeMethodFinderService();

    $result = $service->recursive_method_finder(TestClass::class, ['testMethod'], 1);

    expect($result)->toBeArray();

    // Adjust expectations based on the actual structure
    if (isset($result[0]) && is_array($result[0])) {
        expect($result[0])->toHaveKey(TestClass::class)
            ->and($result[0][TestClass::class])->toBe(['testMethod']);
    } else {
        expect($result)->toHaveKey(TestClass::class)
            ->and($result[TestClass::class])->toBe(['testMethod']);
    }
});
