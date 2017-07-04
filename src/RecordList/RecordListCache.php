<?php

namespace Sintattica\Atk\RecordList;

use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Node;
use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Errors\AtkErrorException;
use Sintattica\Atk\Session\SessionManager;
use Sintattica\Atk\Ui\Page;
use Sintattica\Atk\Utils\DirectoryTraverser;

/**
 * RecordListCache class
 * This class should take care of all the caching of recordlists.
 * Using this you should be able to dramatically improve the performance of your application.
 *
 * It works by storing the HTML output of recordlist in an 'rlcache' directory
 * in the atktempdir.
 * In addition to this you can specify your own 'identifiers' (in your node or on the instance)
 * of this class) in a member variable called 'm_cacheidentifiers'.
 * Use these vars to identify between different situations per node.
 *
 * Example: $this->m_cacheidentifiers=array(array('key'=>'answer','value'=>$answer));
 *
 * @author Boy Baukema <boy@ibuildings.nl>
 */
class RecordListCache
{
    /*
     * The directory where we store the cache
     * @var String
     * @access private
     */
    public $m_cachedir;

    /*
     * The full path of the cachefile
     * @var String
     * @access private
     */
    public $m_cacheid;

    /*
     * The postvars for the recordlist
     * @var array
     * @access private
     */
    public $m_postvars;

    /** @var Node $m_node The node of the recordlist */
    public $m_node;

    /*
     * The cache identifiers
     * These are the variables that make a cacheid unique
     * @var array
     * @access private
     */
    public $m_cacheidentifiers;

    /**
     * The constructor
     * This is a singleton, so please use the getInstance method.
     *
     * @param Node $node The node of the recordlist
     * @param array $postvars The postvars of the recordlist
     */
    public function __construct($node, $postvars = [])
    {
        $this->m_node = $node;
        $this->m_postvars = $postvars;
    }

    /**
     * Setter for the node of the recordlistcache.
     *
     * @param Node $node The node of the recordlist
     */
    public function setNode($node)
    {
        $this->m_node = $node;
    }

    /**
     * Setter for the postvars of the recordlistcache.
     *
     * @param string $postvars The postvars of the recordlist
     */
    public function setPostvars($postvars)
    {
        $this->m_postvars = $postvars;
    }

    /**
     * Gets the cache of the recordlist and registers the appropriate javascript.
     *
     * @return string The cached recordlist
     */
    public function getCache()
    {
        $output = false;
        $this->_setCacheId();

        if (file_exists($this->m_cacheid) && filesize($this->m_cacheid) && !$this->noCaching()) {
            $page = Page::getInstance();

            $page->register_script(Config::getGlobal('assets_url').'javascript/formselect.js');
            $page->register_script(Config::getGlobal('assets_url').'javascript/recordlist.js');

            /*
             * RecordlistCache must call Tools::getUniqueId() too, or the counter will be off.
             */
            Tools::getUniqueId('normalRecordList');

            $sm = SessionManager::getInstance();

            $stackID = $sm->atkStackID();
            $page->register_loadscript(str_replace('*|REPLACESTACKID|*', $stackID, file_get_contents($this->m_cacheid.'_actionloader')));
            $output = str_replace('*|REPLACESTACKID|*', $stackID, file_get_contents($this->m_cacheid));
        }

        return $output;
    }

    /**
     * Makes sure the m_cachedir and the m_cacheid are properly set.
     */
    public function _setCacheId()
    {
        $this->m_cachedir = Config::getGlobal('atktempdir').'rlcache/';
        $identifiers = $this->getIdentifiers();
        $this->m_cacheid = $this->m_cachedir.implode('_', $identifiers).'_'.$this->m_postvars['atkstartat'];

        if (!file_exists($this->m_cachedir) || !is_dir($this->m_cachedir)) {
            mkdir($this->m_cachedir, 0700);
        }
    }

    /**
     * Writes a cached recordlist to the rlcache directory.
     *
     * @param string $output The HTML output of the recordlist
     * @param string $actionloader The actionloader js part of the recordlist
     * @throws AtkErrorException
     */
    public function writeCache($output, $actionloader)
    {
        if (!$this->noCaching()) {
            $sm = SessionManager::getInstance();
            $stackID = $sm->atkStackID();
            $output = str_replace($stackID, '*|REPLACESTACKID|*', $output);
            $actionloader = str_replace($stackID, '*|REPLACESTACKID|*', $actionloader);

            if (file_exists($this->m_cacheid)) {
                unlink($this->m_cacheid);
            }
            $fp = &fopen($this->m_cacheid, 'a+');

            if ($fp) {
                fwrite($fp, $output);
                fclose($fp);
            } else {
                throw new AtkErrorException("Couldn't open {$this->m_cacheid} for writing!");
            }

            $fp = &fopen($this->m_cacheid.'_actionloader', 'a+');
            if ($fp) {
                fwrite($fp, $actionloader);
                fclose($fp);
            } else {
                throw new AtkErrorException("Couldn't open {$this->m_cacheid}_actionloader for writing!");
            }
        }
    }

    /**
     * Wether or not to use caching
     * We don't cache when we are ordering or searching on a recordlist.
     *
     * @return bool Wether or not to use caching
     */
    public function noCaching()
    {
        return $this->m_postvars['atkorderby'] || ($this->m_postvars['atksearch'] && Tools::atk_value_in_array($this->m_postvars['atksearch'])) || ($this->m_postvars['atksmartsearch'] && Tools::atk_value_in_array($this->m_postvars['atksmartsearch']));
    }

    /**
     * Clears the current recordlist cache.
     */
    public function clearCache()
    {
        $cachedir = Config::getGlobal('atktempdir').'rlcache/';
        $atkdirtrav = new DirectoryTraverser();

        $identifiers = $this->getIdentifiers();

        foreach ($atkdirtrav->getDirContents($cachedir) as $cachefile) {
            $unsignificant = false;
            if (!empty($identifiers)) {
                foreach ($identifiers as $identifier) {
                    if (!strstr($cachefile, $identifier)) {
                        $unsignificant = true;
                    }
                }
            }
            if (!in_array($cachefile, array('.', '..')) && !$unsignificant) {
                unlink($cachedir.$cachefile);
            }
        }
    }

    /**
     * Gets all the current identifiers and returns them in an array.
     *
     * @return array The identifiers
     */
    public function getIdentifiers()
    {
        $identifiers = [];
        $identifiers[] = $this->m_node->atkNodeUri().'cache';
        if ($this->m_node->m_cacheidentifiers) {
            $this->_formatIdentifiers($this->m_node->m_cacheidentifiers, $identifiers);
        }
        $this->_formatIdentifiers($this->m_cacheidentifiers, $identifiers);

        return $identifiers;
    }

    /**
     * Formats the identifiers in a '_keyvalue' way.
     *
     * @param array $identifiers The identifiers to format
     * @param array $output The formatted identifiers so far
     *
     * @return array The formatted identifiers
     */
    public function _formatIdentifiers($identifiers, &$output)
    {
        if (count($identifiers) > 0) {
            foreach ($identifiers as $identifier) {
                $output[] = '_'.$identifier['key'].$identifier['value'];
            }

            return $output;
        }
    }

    /**
     * Adds a cache identifier.
     *
     * @param array $identifier The extra cache identifier
     */
    public function addCacheIdentifier($identifier)
    {
        $this->m_cacheidentifiers[] = $identifier;
    }
}
