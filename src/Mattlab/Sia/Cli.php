<?php
namespace Mattlab\Sia;

use Ulrichsg\Getopt;

class Cli
{
    private static $rootPath;

    public static function run($rootPath)
    {
        set_exception_handler(
            function ($exception) {
                Cli::writeln();

                Cli::writeln(
                    '%s:',
                    get_class($exception)
                );

                Cli::writeln($exception->getMessage());
                Cli::writeln();
            }
        );

        self::$rootPath = $rootPath;

        $getopt = new Getopt(
            array(
                array('i', 'input',  Getopt::REQUIRED_ARGUMENT, 'Documentation input directory'),
                array('o', 'output', Getopt::REQUIRED_ARGUMENT, 'Output directory'),
                array('t', 'theme',  Getopt::OPTIONAL_ARGUMENT, 'Theme'),
            )
        );

        $getopt->parse();

        $input  = rtrim($getopt->getOption('i'), DIRECTORY_SEPARATOR);
        $output = rtrim($getopt->getOption('o'), DIRECTORY_SEPARATOR);
        $theme  = self::$rootPath . '/themes/' . ($getopt->getOption('t') ?: 'default');

        if (!file_exists($input)) {
            throw new \RuntimeException('Input directory "' . $input . '" does not exist.');
        }

        if (!file_exists($output)) {
            throw new \RuntimeException('Output directory "' . $output . '" does not exist.');
        }

        if (!file_exists($theme)) {
            throw new \RuntimeException('Theme directory "' . $theme . '" does not exist.');
        }

        $renderer = \Skriv\Markup\Renderer::factory(
            'html',
            array(
                'codeInlineStyles' => true,
            )
        );
        $pages = array();

        foreach (glob($input . '/*.skriv') as $inputFile) {
            $outputFile = str_replace('.skriv', '.html', basename($inputFile));
            $outputPath = $output . '/' . $outputFile;

            Cli::writeln(
                'Read %s',
                $inputFile
            );

            $pages[] = array(
                'output'  => $outputPath,
                'url'     => $outputFile,
                'content' => $renderer->render(
                    file_get_contents($inputFile)
                )
            );
        }

        $rawToc = $renderer->getToc(true);
        unset($renderer);

        foreach ($pages as $key => $page) {
            Cli::writeln(
                'Create %s',
                $pages[$key]['output']
            );

            // renumerate titles
            $pages[$key]['content'] = self::reworkTitles($pages[$key]['content'], $rawToc);

            // typo
            $pages[$key]['content'] = str_replace(
                array(
                    '« ',
                    ' »',
                    ' !',
                    ' ?',
                    ' :',
                ),
                array(
                    '«&nbsp;',
                    '&nbsp;»',
                    '&nbsp;!',
                    '&nbsp;?',
                    '&nbsp;:',
                ),
                $pages[$key]['content']
            );

            // get page title
            $title = $rawToc[$key]['value'] . " - atoum's documentation";

            // generate toc
            $toc = self::generateTocHtmlList($rawToc, $pages);

            // generate previous/next links
            $previous = $next = '';

            if (isset($rawToc[$key - 1])) {
                $previous .= '<i class="icon-chevron-left"></i> ';
                $previous .= '<a href="' . $pages[$key - 1]['url'] . '">' . $rawToc[$key - 1]['value'] . '</a>';
            }

            if (isset($rawToc[$key + 1])) {
                $next .= '<a href="' . $pages[$key + 1]['url'] . '">' . $rawToc[$key + 1]['value'] . '</a>';
                $next .= ' <i class="icon-chevron-right"></i>';
            }

            // write file
            file_put_contents(
                $pages[$key]['output'],
                str_replace(
                    array(
                        '{title}',
                        '{page}',
                        '{toc}',
                        '{previous}',
                        '{next}',
                    ),
                    array(
                        $title,
                        $pages[$key]['content'],
                        $toc,
                        $previous,
                        $next,
                    ),
                    file_get_contents($theme . '/page.html')
                )
            );
        }

        $itemsToCopy = array(
            'css',
            'js',
            'javascript',
            'javascripts',
            'img',
            'image',
            'images',
            'font',
            'fonts',
            '.htaccess'
        );

        foreach ($itemsToCopy as $item) {
            exec("rm -rf $output/$item");

            if (file_exists("$theme/$item")) {
                exec("cp -r $theme/$item $output/");
            }
        }
    }

    public static function reworkTitles($html, $toc, $level = 1, $numerotationPrefix = '')
    {
        $cpt = 0;

        foreach ($toc as $item) {
            $cpt++;

            $currentNumerotation = $numerotationPrefix . $cpt . '.';

            $html = preg_replace(
                sprintf(
                    '#<h%d id="%s">%s</h%d>#',
                    $level,
                    $item['id'],
                    preg_quote($item['value']),
                    $level
                ),
                sprintf(
                    '<h%d id="%s">%s %s%s%s</h%d>',
                    $level,
                    $item['id'],
                    $currentNumerotation,
                    $item['value'],
                    sprintf(
                        '<a class="actionLink icon-link" href="#%s" title="Permalink to this headline"></a>',
                        $item['id']
                    ),
                    '<a class="actionLink icon-th-list" href="#toc" title="Go to table of contents"></a>',
                    $level
                ),
                $html,
                1
            );

            if (isset($item['sub'])) {
                $html = self::reworkTitles($html, $item['sub'], $level + 1, $currentNumerotation);
            }
        }

        return $html;
    }

    public static function generateTocHtmlList($toc, $pages, $level = 0, $numerotationPrefix = '', $page = 0)
    {
        $cpt  = 0;

        $html = '<ul class="toc-list">';

        foreach ($toc as $item) {
            $cpt++;

            $currentNumerotation = $numerotationPrefix . $cpt . '.';

            $html .= '<li>';
            $html .= sprintf(
                '<a id="toc-%s" href="%s#%s">%s %s</a>',
                $item['id'],
                $pages[$page]['url'],
                $item['id'],
                $currentNumerotation,
                $item['value']
            );

            if (isset($item['sub'])) {
                $html .= self::generateTocHtmlList($item['sub'], $pages, $level + 1, $currentNumerotation, $page);
            }

            $html .= '</li>';

            if ($level === 0) {
                $page++;
            }
        }

        return $html . '</ul>';
    }

    public static function writeln()
    {
        $args = func_get_args();

        if (!isset($args[0])) {
            $args[0] = '';
        }

        $args[0] .= PHP_EOL;

        call_user_func_array(array('Mattlab\Sia\Cli', 'write'), $args);
    }

    public static function write()
    {
        $args = func_get_args();

        vprintf($args[0], array_slice($args, 1));
    }
}
