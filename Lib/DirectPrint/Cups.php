<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint;

/**
 * Low level wrapper around the CUPS command line tools (lp, lpstat).
 * This is the only class that knows about CUPS. All commands are executed
 * through proc_open using the array form, so no shell is involved and
 * arguments cannot be interpreted as shell code.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Cups
{
    /**
     * Sends a file to a CUPS queue with the given (already validated) options.
     * Returns ['code' => int, 'output' => string, 'error' => string, 'job_id' => ?string].
     *
     * @param string $queue
     * @param string $file
     * @param array $options
     * @return array
     */
    public static function printFile(string $queue, string $file, array $options): array
    {
        $cmd = ['lp', '-d', $queue];

        $copies = isset($options['copies']) ? (int)$options['copies'] : 1;
        if ($copies > 1) {
            $cmd[] = '-n';
            $cmd[] = (string)$copies;
        }

        if (false === empty($options['media'])) {
            $cmd[] = '-o';
            $cmd[] = 'media=' . $options['media'];
        }

        // portrait is the CUPS default, only the landscape flag is added
        if (($options['orientation'] ?? 'portrait') === 'landscape') {
            $cmd[] = '-o';
            $cmd[] = 'landscape';
        }

        $cmd[] = $file;

        $result = self::exec($cmd);
        $result['job_id'] = self::parseJobId($result['output']);
        return $result;
    }

    /**
     * Returns true if the given queue exists on the CUPS server.
     *
     * @param string $queue
     * @return bool
     */
    public static function queueExists(string $queue): bool
    {
        return in_array($queue, self::queues(), true);
    }

    /**
     * Returns the list of printer queue names available on the CUPS server.
     *
     * @return array
     */
    public static function queues(): array
    {
        $result = self::exec(['lpstat', '-a']);
        if ($result['code'] !== 0 || empty($result['output'])) {
            return [];
        }

        // each line looks like: "queue_name accepting requests since ..."
        $queues = [];
        foreach (explode("\n", $result['output']) as $line) {
            $parts = explode(' ', trim($line));
            if (false === empty($parts[0])) {
                $queues[] = $parts[0];
            }
        }

        return $queues;
    }

    /**
     * Executes a command (array form, no shell) and captures its output.
     *
     * @param array $cmd
     * @return array
     */
    private static function exec(array $cmd): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (false === is_resource($process)) {
            return ['code' => -1, 'output' => '', 'error' => 'cannot execute command'];
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'output' => trim($output), 'error' => trim($error)];
    }

    /**
     * Extracts the CUPS job id from the lp output.
     * Example output: "request id is HP_LaserJet-42 (1 file(s))".
     *
     * @param string $output
     * @return ?string
     */
    private static function parseJobId(string $output): ?string
    {
        return 1 === preg_match('/request id is (\S+)/', $output, $matches)
            ? $matches[1]
            : null;
    }
}
