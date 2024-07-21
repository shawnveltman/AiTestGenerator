<?php

namespace Shawnveltman\AiTestGenerator;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Shawnveltman\LaravelOpenai\ProviderResponseTrait;

class AiTestGenerator
{
    use ProviderResponseTrait;

    public function handle(string $class_name, array $methods)
    {
        $class_file_name = Str::afterLast($class_name, '\\');
        $filename = $class_file_name.now()->format('YmdHis').'Test.php';
        $path = "generated_tests/{$filename}";

        $iterative_helper = new IterativeMethodFinderService();
        $full_results = $iterative_helper->handle($class_name, $methods);

        $prompt = $this->get_prompt($class_name, $methods, $full_results);
        $response = $this->get_response_from_provider(prompt: $prompt, model: 'claude-3-5-sonnet-20240620', json_mode: false);

        Storage::put($path, $response);
    }

    private function get_prompt(string $class_name, array $methods, string $full_results): string
    {
        $class_short_name = Str::afterLast($class_name, '\\');
        $methods_string = implode(', ', $methods);

        return <<<EOD
We're working on a Laravel (V11) project.
Style guide - always use snake_case for method & variable names.
We are using PEST as a test runner - so please write all tests in PEST style and using the PEST assertions & helpers, starting each test with 
```
it('...',function(){
  // test here
)
```

Given that, please write all tests required to give me full coverage of the `{$methods_string}` method(s) of the `{$class_short_name}` class.

Here is the relevant code:
$full_results

Please output ONLY the test code, such that I could copy and paste it into a new PEST testing file and run the tests.

If you need to add context, do it by using the '''//''' or '''/* */''' comment syntax at the top of the class.

Your response should begin with ```<?php ``` and end with 
EOD;
    }
}
