<?php

namespace XLinic\Framework\Core\Module;

class DependencyResolver
{
    /**
     * Resolve module dependencies using topological sort (Kahn's algorithm).
     *
     * @param array<string, ModuleManifest> $modules
     * @return array<ModuleManifest>
     * @throws CircularDependencyException
     */
    public function resolve(array $modules): array
    {
        // Build dependency graph
        $graph = [];
        $inDegree = [];

        // Initialize
        foreach ($modules as $code => $manifest) {
            $graph[$code] = [];
            $inDegree[$code] = 0;
        }

        // Build edges
        foreach ($modules as $code => $manifest) {
            foreach ($manifest->dependencies as $dependency) {
                if (!isset($modules[$dependency])) {
                    throw new \InvalidArgumentException("Missing dependency: {$dependency} required by {$code}");
                }

                $graph[$dependency][] = $code;
                $inDegree[$code]++;
            }
        }

        // Find all modules with no incoming edges
        $queue = [];
        foreach ($inDegree as $code => $degree) {
            if ($degree === 0) {
                $queue[] = $code;
            }
        }

        $result = [];

        // Process queue
        while (!empty($queue)) {
            $current = array_shift($queue);
            $result[] = $modules[$current];

            // For each dependent of current module
            foreach ($graph[$current] as $dependent) {
                $inDegree[$dependent]--;

                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // Check for circular dependencies
        if (count($result) !== count($modules)) {
            $remaining = array_filter($inDegree, fn($degree) => $degree > 0);
            $remainingCodes = array_keys($remaining);

            throw new CircularDependencyException(
                'Circular dependency detected in modules: ' . implode(', ', $remainingCodes)
            );
        }

        return $result;
    }
}

class CircularDependencyException extends \Exception
{
    //
}