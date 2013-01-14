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
            throw new RuntimeException('Input directory "' . $input . '" does not exist.');
        }

        if (!file_exists($output)) {
            throw new RuntimeException('Output directory "' . $output . '" does not exist.');
        }

        if (!file_exists($theme)) {
            throw new RuntimeException('Theme directory "' . $theme . '" does not exist.');
        }

        $renderer = \Skriv\Markup\Renderer::factory();
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

        foreach ($pages as $key => $page) {
            Cli::writeln(
                'Create %s',
                $pages[$key]['output']
            );

            // use FontAwesome for info and warning
            $pages[$key]['content'] = str_replace(
                array(
                    '<div class="info">',
                    '<div class="warning">',
                    '<div class="todo">',
                    '<blockquote>',
                ),
                array(
                    '<div class="message info"><i class="icon-info-sign icon-4x"></i>',
                    '<div class="message warning"><i class="icon-warning-sign icon-4x"></i>',
                    '<div class="message todo"><i class="icon-leaf icon-4x"></i>',
                    '<blockquote class="message"><i class="icon-quote-left icon-4x icon-muted"></i>',
                ),
                $pages[$key]['content']
            );

            // renumerate titles
            $pages[$key]['content'] = self::reworkTitles($pages[$key]['content'], $rawToc);

            // geshi
            // $geshi = new GeSHi();
            // $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
            // $geshi->set_header_type(GESHI_HEADER_DIV);

            // preg_match_all('#<pre class="([^"]+)">(.*)</pre>#sU', $pages[$key]['content'], $matches);

            // foreach ($matches[0] as $k => $match) {
            //     $geshi->set_language($matches[1][$k], true);
            //     if ($geshi->error() === false) {
            //         $geshi->set_source(trim(html_entity_decode($matches[2][$k])));

            //         $pages[$key]['content'] = str_replace(
            //             $match,
            //             '<div class="code">' . $geshi->parse_code() . '</div>',
            //             $pages[$key]['content']
            //         );
            //     }
            // }

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

        foreach (array('css', 'js', 'javascript', 'javascripts', 'img', 'image', 'images', 'font', 'fonts') as $dir) {
            exec("rm -rf $output/$dir");

            if (file_exists("$theme/$dir")) {
                exec("cp -rf $theme/$dir $output/");
            }
        }
    }

    public static function reworkTitles($html, $array, $level = 1, $numerotationPrefix = '')
    {
        $cpt = 0;

        foreach ($array as $item) {
            $cpt++;

            $currentNumerotation = $numerotationPrefix . $cpt . '.';

            $html = str_replace(
                sprintf(
                    '<h%d id="%s">%s</h%d>',
                    $level,
                    $item['id'],
                    $item['value'],
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
                    '<a class="actionLink icon-circle-arrow-up" href="#top" title="Go to top of page"></a>',
                    $level
                ),
                $html
            );

            if (isset($item['sub'])) {
                $html = self::reworkTitles($html, $item['sub'], $level + 1, $currentNumerotation);
            }
        }

        return $html;
    }

    public static function generateTocHtmlList($array, $pages, $level = 0, $numerotationPrefix = '', $page = 0)
    {
        $cpt  = 0;

        $html = '<ul class="toc-list">';

        foreach ($array as $item) {
            $cpt++;

            $currentNumerotation = $numerotationPrefix . $cpt . '.';

            $html .= '<li>';
            $html .= sprintf(
                '<a href="%s#%s">%s %s</a>',
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
