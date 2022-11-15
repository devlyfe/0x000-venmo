<?php

namespace App\Commands;

use App\Enums\VenmoStatus;
use App\Service\Venmo;
use LaravelZero\Framework\Commands\Command;
use Spatie\Fork\Fork;

use function Termwind\render;

class DefaultCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'default';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start a venmo check';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $listFile = 'list.txt';

        if (!file_exists($listFile)) {
            return render(<<<KONTOL
                <div class="px-3 pt-2 text-red-500">
                    Masukin list yg bener titit.
                </div>
            KONTOL);
        }

        $lists = collect(file($listFile))
            ->map(fn ($list) => str($list)->trim())
            ->map(function (\Illuminate\Support\Stringable $list) {
                [$email, $password] = $list->explode('|')->toArray();

                // create list dir if not exists
                $resultDir = getcwd() . '/result';
                if (!is_dir($resultDir)) {
                    @mkdir($resultDir);
                }

                return function () use ($email, $password, $resultDir) {
                    $status = VenmoStatus::ERROR;
                    $resultMessage = implode('|', [$email, $password]);

                    try {
                        $venmo = new Venmo($email, $password);
                        $result = $venmo->handle();

                        /**
                         * @var \App\Enums\VenmoStatus $status
                         */
                        $status = $result->status;
                        $message = $result->message;
                        $data = $result->data;

                        $resultMessage = implode('|', $data) . ($message ? '|' . $message : '');
                    } catch (\Throwable $th) {
                        $resultMessage = $resultMessage . '|' . $th->getMessage();
                    }

                    @file_put_contents(
                        $resultDir . '/' . $status->value . '.txt',
                        $resultMessage . PHP_EOL,
                        FILE_APPEND
                    );

                    // writing output
                    $textColor = $status != VenmoStatus::LIVE ? 'text-red-500' : 'text-green-500';

                    return render(<<<KONTOL
                        <div class="{$textColor} pl-3">
                            {$resultMessage}
                        </div>
                    KONTOL);
                };
            });

        // forking the jobs
        Fork::new()->run(...$lists->toArray());
    }
}
