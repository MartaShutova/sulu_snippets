<?php

namespace ContentBundle\Twig\Extension;

use Sulu\Component\Content\Compat\Structure\PageBridge;
use Symfony\Component\DependencyInjection\Container;

class ConversionElements extends \Twig_Extension
{

    private $container;

    private $isArticle = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }


    public function getName()
    {
        return 'getConversionElements';
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction(
                'getConversionElements',
                array($this, 'getConversionElements')

            ),
        );
    }

    public function getConversionElements($convElem, $uuid, $locale = 'en')
    {
        $page = $this->container->get('flo.page')->getPage($uuid);

        if (isset($page['template']) && in_array($page['template'], $this->container->get('flo.article')->getTemplateNames())) {
            $this->isArticle = true;
        }

        $snippets =  $this->container->get('sulu_snippet.repository')->getSnippets($locale, $convElem, null, null, null, 'created', 'DESC');

        if($page && $snippets) {
            foreach ($snippets as $s) {

                $snippet = $s->getStructure()->toArray();

                if ($s->getLocale() == $locale && $this->checkSnippet($uuid, $page, $snippet)) {
                    
                    return $snippet;
                }

            }

        }

        return false;

    }

    /**
     * @param string $uuid
     * @param array $page
     * @param array $snippet
     * @return bool
     */
    public function checkSnippet($uuid, $page, $snippet) {

        if (!$this->checkAvailable($snippet) || $this->checkExcludePages($uuid,$snippet)) return false;

        if ($this->checkIncludePages($uuid,$snippet) || $this->checkIncludeArticles($uuid,$snippet) || $this->checkTags($snippet, $page) || $this->checkKeywords($snippet,$page)) return true;

        return false;
    }

    /**
     * @param array $snippet
     * @return bool
     */
    public function checkAvailable(array $snippet)
    {
       return isset($snippet['on']) && $snippet['on'];
    }


    /**
     * @param string $id
     * @param array $snippet
     * @return bool
     */
    public function checkExcludePages($id, $snippet){

        if (count($snippet['excludePages']) > 0) {

            if ( $this->findPage($id, $snippet['excludePages'])) return true;

        }

        return false;

    }

    /**
     * @param string $id
     * @param array $snippet
     * @return bool
      */
    public function checkIncludePages($id, $snippet){

        if (count($snippet['includePages']) == 0) return false;

        $uuid = $id;
        if ($this->isArticle) {
            $uuid = $this->container->get('flo.article')->getArticleParentUid($id);
        }

        if ( $this->findPage($uuid, $snippet['includePages'])) return true;

        if ($parentUuids = $this->container->get('flo.page')->getParentsByUuid($uuid)){

            foreach ($parentUuids as $parentUuid) {

                if ( $this->findPage($parentUuid, $snippet['includePages'])) return true;

            }

        }

        return false;

    }

    /**
     * @param $id
     * @param $snippet
     * @return bool
     */
    public function checkIncludeArticles($id, $snippet){

        if (count($snippet['articles']) == 0) return false;


        if ($this->isArticle) {

            foreach ($snippet['articles'] as $article) {

                if ( $article ==  $id ) return true;

            }

        }

        return false;

    }

    /**
     * @param string $id
     * @param array $snippetPages
     * @return bool
     */
    public function findPage($id, $snippetPages){

        if (in_array($id, $snippetPages)) return true;

        return false;

    }

    /**
     * @param array $snippet
     * @param array $page
     * @return bool
     */
    public function checkTags($snippet, $page) {

        if (isset($page['content']['tags'])){

            if (count($snippet['tags']) > 0 && count($page['content']['tags']) > 0) {

                foreach ($snippet['tags'] as $snippetTag) {

                    if (in_array($snippetTag, $page['content']['tags'])) return true;

                }

            }

        }

        return false;

    }

    /**
     * @param array $snippet
     * @param array $page
     * @return bool
     */
    public function checkKeywords($snippet, $page) {

        if ($snippet['keyword'] !== '' && $page['content']['title'] !== '') {

            $snippetKeywords = explode(' ', preg_replace('/[^A-Za-z0-9 ]/', '', $snippet['keyword']));

            $titleStr = $page['content']['title'] . ((isset($page['content']['h1_title'])) ?  ' '. $page['content']['h1_title'] : '');

            $pageKeywords = preg_split("/[\s]+/", preg_replace('/[^A-Za-z0-9 ]/', '', $titleStr));

            return count(array_intersect(array_map('strtolower', $pageKeywords), array_map('strtolower', $snippetKeywords))) > 0;

        }

        return false;
    }

}
