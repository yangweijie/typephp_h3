<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Pest binds every test closure to PHPUnit\Framework\TestCase by default,
| which matches this project: class-based tests live under the
| H3Php\Tests\* namespace and extend PHPUnit\Framework\TestCase directly.
|
| Note: there is deliberately no pest()->extend(...) binding here. The
| default Pest scaffolding wires Tests\TestCase, but this project has no
| Tests\ namespace / autoload mapping, so binding it makes Pest abort with
| "The class or trait [Tests\TestCase] could not be found".
|
*/

/*
|--------------------------------------------------------------------------
| Model directory
|--------------------------------------------------------------------------
|
| Weights are not vendored, so tests that need a real MiniMax-H3 model tree
| must opt in. H3_MODEL_DIR plays the same role as the CLI's -d/--model-dir
| option; when it is absent those tests are skipped instead of failing.
|
|   H3_MODEL_DIR=/path/to/MiniMax-H3 vendor/bin/pest
|
| Usage (functional tests):
|   test('...')->skip(fn () => null === model_dir(), 'needs H3_MODEL_DIR');
|   // or, inside the body:
|   skip_without_model_dir();
|
| Usage (class-based tests):
|   if (null === model_dir()) {
|       $this->markTestSkipped('Needs a model tree: set H3_MODEL_DIR.');
|   }
|
*/

function model_dir(): ?string
{
    $dir = getenv('H3_MODEL_DIR');

    return is_string($dir) && '' !== $dir && is_dir($dir) ? $dir : null;
}

function skip_without_model_dir(): void
{
    if (null === model_dir()) {
        test()->skip('Needs a model tree: set H3_MODEL_DIR (same as CLI -d).');
    }
}
