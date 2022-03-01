<?php

namespace Crosf32\ControllerRefactorer\Helper;

use ReflectionClass;
use RuntimeException;

class ExtendedReflectionClass extends ReflectionClass
{
    protected array $useStatements = [];

    protected bool $useStatementsParsed = false;

    protected function parseUseStatements(): array
    {
        if ($this->useStatementsParsed) {
            return $this->useStatements;
        }

        if (!$this->isUserDefined()) throw new RuntimeException('Must parse use statements from user defined classes.');

        $source = $this->readFileSource();
        $this->useStatements = $this->tokenizeSource($source);

        $this->useStatementsParsed = true;

        return $this->useStatements;
    }

    private function readFileSource(): string
    {
        $file = fopen($this->getFileName(), 'r');
        $line = 0;
        $source = '';

        while (!feof($file)) {
            ++$line;

            if ($line >= $this->getStartLine()) {
                break;
            }

            $source .= fgets($file);
        }

        fclose($file);

        return $source;
    }

    private function tokenizeSource($source): array
    {
        $tokens = token_get_all($source);

        $isInUse = false;
        $currentIndex = 0;
        $currentUse = '';
        $useStatements = [];

        foreach ($tokens as $index => $token) {
            if (';' === $token && $isInUse) {
                $useStatements[] = $currentUse . ';';
                $isInUse = false;
            }

            if(is_array($token) && $token[0] === T_USE) {
                $isInUse = true;
                $currentUse = '';
            }

            if ($isInUse) {
                $currentUse .= $token[1];
            }
        }

        return $useStatements;
    }

    public function getUseStatements(): array
    {
        return $this->parseUseStatements();
    }
}
