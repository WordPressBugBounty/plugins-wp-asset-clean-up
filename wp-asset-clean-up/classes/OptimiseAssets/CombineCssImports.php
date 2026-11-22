<?php
/** @noinspection OffsetOperationsInspection */
/** @noinspection SlowArrayOperationsInLoopInspection */

namespace WpAssetCleanUp\OptimiseAssets;

if ( ! class_exists('\MatthiasMullieWpacu\Minify\Minify') ) {
    require_once WPACU_PLUGIN_DIR . '/vendor/autoload.php';
}

/**
 * @phpstan-consistent-constructor
 *
 * Combine CSS Imports extended from CSS minifier
 *
 * Please report bugs on https://github.com/matthiasmullie/minify/issues
 *
 * @package Minify
 * @author Matthias Mullie <minify@mullie.eu>
 * @author Tijs Verkoyen <minify@verkoyen.eu>
 * @copyright Copyright (c) 2012, Matthias Mullie. All rights reserved
 * @license MIT License
 */
class CombineCssImports extends \MatthiasMullieWpacu\Minify\Minify
{
	/**
	 * @var int maximum import size in kB
	 */
	protected $maxImportSize = 5;

	/**
	 * @var string[] valid import extensions
     * @noinspection PropertyCanBeStaticInspection
     */
	protected $importExtensions = array(
		'gif' => 'data:image/gif',
		'png' => 'data:image/png',
		'jpe' => 'data:image/jpeg',
		'jpg' => 'data:image/jpeg',
		'jpeg' => 'data:image/jpeg',
		'svg' => 'data:image/svg+xml',
		'woff' => 'data:application/x-font-woff',
		'tif' => 'image/tiff',
		'tiff' => 'image/tiff',
		'xbm' => 'image/x-xbitmap',
	);

	/**
	 * Set the maximum size if files to be imported.
	 *
	 * Files larger than this size (in kB) will not be imported into the CSS.
	 * Importing files into the CSS as data-uri will save you some connections,
	 * but we should only import relatively small decorative images so that our
	 * CSS file doesn't get too bulky.
	 *
	 * @param int $size Size in kB
	 */
	public function setMaxImportSize($size)
	{
		$this->maxImportSize = $size;
	}

	/**
	 * Set the type of extensions to be imported into the CSS (to save network
	 * connections).
	 * Keys of the array should be the file extensions & respective values
	 * should be the data type.
	 *
	 * @param string[] $extensions Array of file extensions
	 */
	public function setImportExtensions(array $extensions)
	{
		$this->importExtensions = $extensions;
	}

    /**
     * Expand only leading, unconditional relative CSS imports. An unrecognized rule
     * anywhere in the import tree requires the browser to load the original sheet.
     *
     * @param string $source
     * @param string $content
     * @param array $parents
     *
     * @return string|false
     */
    protected function combineImports($source, $content, $parents)
    {
        $offset  = 0;
        $prefix  = '';
        $pattern = '~\G(\s*)@import\s+(?:url\(\s*(["\']?)([a-z0-9_./-]+\.css)\2\s*\)|(["\'])([a-z0-9_./-]+\.css)\4)\s*;~i';

        while (preg_match($pattern, $content, $match, 0, $offset)) {
            $relative = isset($match[5]) ? $match[5] : $match[3];
            if ($source === '' || $relative[0] === '/') {
                return false;
            }

            $path      = dirname($source) . '/' . $relative;
            $canonical = realpath($path);
            if ( ! $canonical || isset($parents[$canonical]) || count($parents) >= 16 || ! $this->canImportFile($path) ) {
                return false;
            }

            $child = $this->load($path);
            if (OptimizeCss::hasImportRule($child)) {
                $child = $this->combineImports($path, $child, $parents + array($canonical => true));
                if ($child === false) {
                    return false;
                }
            }
            // An imported file's namespace and character encoding are sheet-scoped.
            if (stripos($child, '@namespace') !== false || stripos($child, '@charset') !== false) {
                return false;
            }
            $child   = $this->importFiles($path, $child);
            $prefix .= $match[1] . $this->move($this->getPathConverter($path, $source), $child);
            $offset += strlen($match[0]);
        }

        $rest = substr($content, $offset);
        if (OptimizeCss::hasImportRule($rest) || stripos($rest, '@namespace') !== false) {
            return false;
        }
        return $prefix . $rest;
    }

	/**
	 * Import files into the CSS, base64-sized.
	 *
	 * url(image.jpg) images will be loaded and their content merged into the
	 * original file, to save HTTP requests.
	 *
	 * @param string $source  The file to import files for
	 * @param string $content The CSS content to import files for
	 *
	 * @return string
	 */
	protected function importFiles($source, $content)
	{
		$regex = '/url\((["\']?)(.+?)\\1\)/i';
		if ($this->importExtensions && preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
			$search = array();
			$replace = array();

			// loop the matches
			foreach ($matches as $match) {
				$extension = substr(strrchr($match[2], '.'), 1);
				if ($extension && !array_key_exists($extension, $this->importExtensions)) {
					continue;
				}

				// get the path for the file that will be imported
				$path = $match[2];
				$path = dirname($source).'/'.$path;

				// only replace the import with the content if we're able to get
				// the content of the file, and it's relatively small
				if ($this->canImportFile($path) && $this->canImportBySize($path)) {
					// grab content && base64-ize
					$importContent = $this->load($path);
					$importContent = base64_encode($importContent);

					// build replacement
					$search[] = $match[0];
					$replace[] = 'url('.$this->importExtensions[$extension].';base64,'.$importContent.')';
				}
			}

			// replace the import statements
			$content = str_replace($search, $replace, $content);
		}

		return $content;
	}

	/**
	 * Perform CSS optimizations.
	 *
	 * @param string|null $path    Path to write the data to
	 * @param array $parents Parent paths, for circular reference checks
	 *
	 * @return string The minified data
	 */
	public function execute($path = null, $parents = array())
	{
		$content = '';

        foreach ($this->data as $source => $css) {
            $source   = is_int($source) ? '' : $source;
            $content .= $this->resolveStylesheet($source, $css, $path, $parents);
        }
        return $content;
    }

    /**
     * Use the bytes read for this source, returning them intact on fallback.
     *
     * @param string $source
     * @param string $css
     * @param string|null $target
     * @param array $parents
     *
     * @return string
     */
    public function resolveStylesheet($source, $css, $target = null, $parents = array())
    {
        $resolved = $this->combineImports($source, $css, $parents + array(realpath($source) => true));
        if ($resolved === false) {
            return $css;
        }

        $resolved = $this->importFiles($source, $resolved);
        return $this->move($this->getPathConverter($source, $target ?: $source), $resolved);
    }

	/**
	 * Moving a css file should update all relative urls.
	 * Relative references (e.g. ../images/image.gif) in a certain css file,
	 * will have to be updated when a file is being saved at another location
	 * (e.g. ../../images/image.gif, if the new CSS file is 1 folder deeper).
	 *
	 * @param \MatthiasMullieWpacu\PathConverter\ConverterInterface $converter Relative path converter
	 * @param string             $content   The CSS content to update relative urls for
	 *
	 * @return string
	 */
	protected function move(\MatthiasMullieWpacu\PathConverter\ConverterInterface $converter, $content)
	{
		/*
		 * Relative path references will usually be enclosed by url(). @import
		 * is an exception, where url() is not necessary around the path (but is
		 * allowed).
		 * This *could* be 1 regular expression, where both regular expressions
		 * in this array are on different sides of a |. But we're using named
		 * patterns in both regexes, the same name on both regexes. This is only
		 * possible with a (?J) modifier, but that only works after a fairly
		 * recent PCRE version. That's why I'm doing 2 separate regular
		 * expressions & combining the matches after executing of both.
		 */
		$relativeRegexes = array(
			// url(xxx)
			'/
            # open url()
            url\(

                \s*

                # open path enclosure
                (?P<quotes>["\'])?

                    # fetch path
                    (?P<path>.+?)

                # close path enclosure
                (?(quotes)(?P=quotes))

                \s*

            # close url()
            \)

            /ix',

			// @import "xxx"
			'/
            # import statement
            @import

            # whitespace
            \s+

                # we don\'t have to check for @import url(), because the
                # condition above will already catch these

                # open path enclosure
                (?P<quotes>["\'])

                    # fetch path
                    (?P<path>.+?)

                # close path enclosure
                (?P=quotes)

            /ix',
		);

		// find all relative urls in css
		$matches = array();
		foreach ($relativeRegexes as $relativeRegex) {
			if (preg_match_all($relativeRegex, $content, $regexMatches, PREG_SET_ORDER)) {
				$matches = array_merge($matches, $regexMatches);
			}
		}

		$search = array();
		$replace = array();

		// loop all urls
		foreach ($matches as $match) {
			// determine if it's an url() or an @import match
			$type = (strncmp($match[0], '@import', 7) === 0 ? 'import' : 'url');

			$url = $match['path'];
            if ( ! $this->canImportByPath($url) ) {
                continue;
            }

            // Convert just the path, preserving the original query and fragment.
            $pathLength = strcspn($url, '?#');
            $url        = $converter->convert(substr($url, 0, $pathLength)) . substr($url, $pathLength);

			/*
			 * Urls with control characters above 0x7e should be quoted.
			 * According to Mozilla's parser, whitespace is only allowed at the
			 * end of unquoted urls.
			 * Urls with `)` (as could happen with data: uris) should also be
			 * quoted to avoid being confused for the url() closing parentheses.
			 * And urls with a # have also been reported to cause issues.
			 * Urls with quotes inside should also remain escaped.
			 *
			 * @see https://developer.mozilla.org/nl/docs/Web/CSS/url#The_url()_functional_notation
			 * @see https://hg.mozilla.org/mozilla-central/rev/14abca4e7378
			 * @see https://github.com/matthiasmullie/minify/issues/193
			 */
			$url = trim($url);
			if (preg_match('/[\s\)\'"#\x{7f}-\x{9f}]/u', $url)) {
				$url = $match['quotes'] . $url . $match['quotes'];
			}

			// build replacement
			$search[] = $match[0];
			if ($type === 'url') {
				$replace[] = 'url('.$url.')';
			} elseif ($type === 'import') {
				$replace[] = '@import "'.$url.'"';
			}
		}

		// replace urls
		return str_replace($search, $replace, $content);
	}

	/**
	 * Check if file is small enough to be imported.
	 *
	 * @param string $path The path to the file
	 *
	 * @return bool
	 */
	protected function canImportBySize($path)
	{
		return ($size = @filesize($path)) && $size <= $this->maxImportSize * 1024;
	}

	/**
	 * Check if file a file can be imported, going by the path.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	protected function canImportByPath($path)
	{
        $path = ltrim($path);
        return $path !== '' && $path[0] !== '/' && $path[0] !== '#' &&
            ! preg_match('~^[a-z][a-z0-9+.-]*:~i', $path);
	}

	/**
	 * Return a converter to update relative paths to be relative to the new
	 * destination.
	 *
	 * @param string $source
	 * @param string $target
	 *
	 * @return \MatthiasMullieWpacu\PathConverter\Converter
	 */
	protected function getPathConverter($source, $target)
	{
		return new \MatthiasMullieWpacu\PathConverter\Converter($source, $target);
	}
}
