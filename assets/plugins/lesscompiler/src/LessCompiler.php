<?php

use ILess\Parser;
use ILess\FunctionRegistry;
use ILess\Node\ColorNode;
use ILess\Node\DimensionNode;

require __DIR__ . '/../lib/ILess/Autoloader.php';
ILess\Autoloader::register();

class LessCompiler
{
    private $cssPath         = '/assets/templates/default/css/';
    private $hashesPath      = MODX_BASE_PATH . 'assets/cache/less_hashes/';
    private $variablesFile   = MODX_BASE_PATH . 'assets/templates/default/css/variables.json';
    private $prependedStyles = '';

    public function __construct($params = []) {
        if (!empty($params['path'])) {
            $this->cssPath = $params['path'];
        }

        $this->cssPath = trim($this->cssPath, '/') . '/';

        if (!empty($params['vars'])) {
            $this->variablesFile = MODX_BASE_PATH . ltrim($params['vars'], '/');
        }

        if (!is_dir($this->hashesPath)) {
            mkdir($this->hashesPath, 0777, true);
        }
    }

    private function prependStyles($style)
    {
        $this->prependedStyles = $style . $this->prependedStyles;
    }

    private function isFilesChanged($files = [])
    {
        $isChanged = false;

        foreach ($files as $file) {
            if (is_readable($file)) {
                $basename = pathinfo($file, PATHINFO_BASENAME);
                $hash     = hash('md5', file_get_contents($file));
                $hashFile = $this->hashesPath . $basename . '.hash';

                if (file_exists($hashFile) && $hash == file_get_contents($hashFile)) {
                    continue;
                }

                file_put_contents($hashFile, $hash);
                $isChanged = true;
            }
        }

        return $isChanged;
    }

    private function compileFiles($files)
    {
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);

            $parser = new Parser([
                'sourceMap' => true,
                'compress'  => true,
            ]);

            if (!empty($this->prependedStyles)) {
                $parser->parseString($this->prependedStyles);
            }

            $cssFile = $filename . '.css';
            $mapFile = $this->cssPath . $cssFile . '.map';

            $parser->getContext()->sourceMapOptions = [
                'sourceRoot' => MODX_BASE_URL,
                'filename'   => $cssFile,
                'url'        => MODX_BASE_URL . $mapFile,
                'write_to'   => MODX_BASE_PATH . $mapFile,
                'base_path'  => MODX_BASE_PATH,
            ];

            $parser->parseFile($file);

            foreach (glob(MODX_BASE_PATH . $this->cssPath . '_' . $filename . '_*.less') as $subfile) {
                $parser->parseFile($subfile);
            }

            file_put_contents(MODX_BASE_PATH . $this->cssPath . $cssFile, $parser->getCSS());
        }
    }

    public function makeCSS() {
        $shouldCompile = false;

        $sources = glob(MODX_BASE_PATH . $this->cssPath . '*.less');

        if (is_readable($this->variablesFile)) {
            $shouldCompile = $this->isFilesChanged([$this->variablesFile]);
            $json = json_decode(file_get_contents($this->variablesFile), true);
            $styles = '';

            foreach ($json as $key => $value) {
                $styles .= "@$key: $value;\n";
            }

            $this->prependStyles($styles);
        }

        $shouldCompile = $this->isFilesChanged($sources) || $shouldCompile;

        if ($shouldCompile) {
            $sources = array_filter($sources, function($filename) {
                return strpos(pathinfo($filename, PATHINFO_BASENAME), '_') !== 0;
            });

            $this->compileFiles($sources);
        }
    }

    public function clearHashes()
    {
        foreach (glob($this->hashesPath . '*.hash') as $file) {
            unlink($file);
        }
    }
}
