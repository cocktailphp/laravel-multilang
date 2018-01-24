<?php
/*
 * This file is part of the Laravel MultiLang package.
 *
 * (c) Avtandil Kikabidze aka LONGMAN <akalongman@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Longman\LaravelMultiLang;

use Closure;
use Illuminate\Cache\CacheManager as Cache;
use Illuminate\Database\DatabaseManager as Database;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Translator;

class MultiLang
{
    /**
     * Language/Locale.
     *
     * @var string
     */
    protected $lang;

    /**
     * System environment
     *
     * @var string
     */
    protected $environment;

    /**
     * Config.
     *
     * @var \Longman\LaravelMultiLang\Config
     */
    protected $config;

    /**
     * Repository
     *
     * @var \Longman\LaravelMultiLang\Repository
     */
    protected $repository;

    /**
     * Texts.
     *
     * @var array
     */
    protected $texts;

    /**
     * Missing texts.
     *
     * @var array
     */
    protected $new_texts;

    /**
     * Application scope.
     *
     * @var string
     */
    protected $scope = 'global';

    /**
     * Translator instance
     *
     * @var \Symfony\Component\Translation\Translator
     */
    protected $translator;

    /**
     * Create a new MultiLang instance.
     *
     * @param string $environment
     * @param array $config
     * @param \Illuminate\Cache\CacheManager $cache
     * @param \Illuminate\Database\DatabaseManager $db
     */
    public function __construct(string $environment, array $config, Cache $cache, Database $db)
    {
        $this->environment = $environment;

        $this->setConfig($config);

        $this->setRepository(new Repository($this->config, $cache, $db));
    }

    /**
     * Set multilang config
     *
     * @param array $config
     * @return $this
     */
    public function setConfig(array $config): MultiLang
    {
        $this->config = new Config($config);

        return $this;
    }

    /**
     * Get multilang config
     *
     * @return \Longman\LaravelMultiLang\Config
     */
    public function getConfig(): Config
    {

        return $this->config;
    }

    /**
     * Set repository object
     *
     * @param \Longman\LaravelMultiLang\Repository $repository
     * @return $this
     */
    public function setRepository(Repository $repository): MultiLang
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * Get repository object
     *
     * @return \Longman\LaravelMultiLang\Repository
     */
    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * Set application scope
     *
     * @param $scope
     * @return $this
     */
    public function setScope($scope): MultiLang
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get application scope
     *
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Set locale
     *
     * @param  string $lang
     * @return void
     */
    public function setLocale(string $lang)
    {
        if (! $lang) {
            throw new InvalidArgumentException('Locale is empty');
        }
        $this->lang = $lang;
    }

    public function loadTexts(string $locale = null, string $scope = null): array
    {
        if (is_null($locale)) {
            $locale = $this->getLocale();
        }

        if (is_null($scope)) {
            $scope = $this->getScope();
        }

        if ($this->environment != 'production' || $this->config->get('cache.enabled', true) === false) {
            $texts = $this->repository->loadFromDatabase($locale, $scope);
        } else {
            if ($this->repository->existsInCache($locale, $scope)) {
                $texts = $this->repository->loadFromCache($locale, $scope);
            } else {
                $texts = $this->repository->loadFromDatabase($locale, $scope);
                $this->repository->storeInCache($locale, $texts, $scope);
            }
        }

        $this->createTranslator($locale, $scope, $texts);

        $this->texts = $texts;

        return $texts;
    }

    protected function createTranslator(string $locale, string $scope, array $texts): Translator
    {
        $this->translator = new Translator($locale, new MessageSelector());
        $this->translator->addLoader('array', new ArrayLoader());
        $this->translator->addResource('array', $texts, $locale, $scope);

        return $this->translator;
    }

    /**
     * Get translated text
     *
     * @param  string $key
     * @param  array $replace
     * @return string
     */
    public function get(string $key, array $replace = []): string
    {
        if (empty($key)) {
            throw new InvalidArgumentException('String key not provided');
        }

        if (! $this->lang) {
            return $key;
        }

        if (is_null($this->texts)) {
            // Load texts from storage
            $this->loadTexts();
        }

        if (! isset($this->texts[$key])) {
            $this->queueToSave($key);
        }

        return $this->translator->trans($key, $replace, $this->getScope());
    }

    /**
     * Get redirect url in middleware
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    public function getRedirectUrl(Request $request): string
    {
        $exclude_patterns = $this->config->get('exclude_segments', []);
        if (! empty($exclude_patterns)) {
            if (call_user_func_array([$request, 'is'], $exclude_patterns)) {
                return '';
            }
        }

        $locale = $request->segment(1);
        $fallback_locale = $this->config->get('default_locale', 'en');
        if (! empty($locale) && strlen($locale) == 2) {
            $locales = $this->config->get('locales', []);

            if (! isset($locales[$locale])) {
                $segments = $request->segments();
                $segments[0] = $fallback_locale;
                $url = implode('/', $segments);
                if ($query_string = $request->server->get('QUERY_STRING')) {
                    $url .= '?' . $query_string;
                }

                return $url;
            }
        } else {
            $segments = $request->segments();
            $url = $fallback_locale . '/' . implode('/', $segments);
            if ($query_string = $request->server->get('QUERY_STRING')) {
                $url .= '?' . $query_string;
            }

            return $url;
        }

        return '';
    }

    /**
     * Detect locale based on url segment
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    public function detectLocale(Request $request): string
    {
        $locale = $request->segment(1);
        $locales = $this->config->get('locales');

        if (isset($locales[$locale])) {
            return isset($locales[$locale]['locale']) ? $locales[$locale]['locale'] : $locale;
        }

        return (string) $this->config->get('default_locale', 'en');
    }

    /**
     * Wrap routes to available languages group
     *
     * @param \Closure $callback
     * @return void
     */
    public function routeGroup(Closure $callback)
    {
        $router = app('router');

        $locales = $this->config->get('locales', []);

        foreach ($locales as $locale => $val) {
            $router->group([
                'prefix' => $locale,
                'as'     => $locale . '.',
            ], $callback);
        }
    }

    /**
     * Get texts
     *
     * @return array
     */
    public function getTexts(): array
    {

        return $this->texts;
    }

    /**
     * Get all texts
     *
     * @param string $lang
     * @param string $scope
     * @return array
     */
    public function getAllTexts(string $lang = null, string $scope = null): array
    {
        return $this->repository->loadAllFromDatabase($lang, $scope);
    }

    /**
     * Set texts manually
     *
     * @param  array $texts_array
     * @return \Longman\LaravelMultiLang\MultiLang
     */
    public function setTexts(array $texts_array): MultiLang
    {
        $texts = [];
        foreach ($texts_array as $key => $value) {
            $texts[$key] = $value;
        }

        $this->texts = $texts;

        $this->createTranslator($this->getLocale(), $this->getScope(), $texts);

        return $this;
    }

    /**
     * Queue missing texts
     *
     * @param  string $key
     * @return void
     */
    protected function queueToSave(string $key)
    {
        $this->new_texts[$key] = $key;
    }

    /**
     * Get language prefixed url
     *
     * @param string $path
     * @param string $lang
     * @return string
     */
    public function getUrl(string $path, string $lang = null): string
    {
        $locale = $lang ? $lang : $this->getLocale();
        if ($locale) {
            $path = $locale . '/' . $this->removeLocaleFromPath($path);
        }

        return $path;
    }

    /**
     * Remove locale from the path
     *
     * @param string $path
     * @return string
     */
    private function removeLocaleFromPath(string $path): string
    {
        $locales = $this->config->get('locales');
        $locale = mb_substr($path, 0, 2);
        if (isset($locales[$locale])) {
            return mb_substr($path, 3);
        }

        return $path;
    }

    /**
     * Get language prefixed route
     *
     * @param string $name
     * @return string
     */
    public function getRoute(string $name): string
    {
        $locale = $this->getLocale();
        if ($locale) {
            $name = $locale . '.' . $name;
        }

        return $name;
    }

    /**
     * Check if autosave allowed
     *
     * @return bool
     */
    public function autoSaveIsAllowed()
    {
        if ($this->environment == 'local' && $this->config->get('db.autosave', true)) {
            return true;
        }

        return false;
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->lang;
    }

    /**
     * Get available locales
     *
     * @return array
     */
    public function getLocales(): array
    {
        return (array) $this->config->get('locales');
    }

    /**
     * Save missing texts
     *
     * @return bool
     */
    public function saveTexts(): bool
    {
        if (empty($this->new_texts)) {
            return false;
        }

        return $this->repository->save($this->new_texts, $this->scope);
    }
}
