<?php
/**
 * @copyright 2009-2023 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Vanilla\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

/**
 * Command to validate parts of our config.
 *
 * To make a part of the config validatable add this into your config:
 *
 * @example
 * return [
 *     "my_key" => env("SOME_ENV"),
 *
 *     ValidateConfigCommand::KEY => ["my_key" => ["required"]],
 * ]
 *
 * Standard laravel validation configs apply.
 */
class ValidateConfigCommand extends Command
{
    const KEY = "__validation__";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "config:validate";

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = "Validate the application config.";

    /**
     * @return int
     */
    public function handle(): int
    {
        $allConfig = Config::all();
        foreach ($allConfig as $key => $value) {
            $validationRules = $value[self::KEY] ?? null;
            if ($validationRules === null) {
                continue;
            }

            $this->components->task("Validating '$key' config.");
            try {
                Validator::validate($value, $validationRules);
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $errorMessages = $exception->validator->errors()->all();
                foreach ($errorMessages as $errorMessage) {
                    $this->components->error($errorMessage);
                }
                return 1;
            }
        }
        $this->components->info("Config validation passed!");
        return 0;
    }
}
