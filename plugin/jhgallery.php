<?php

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.html.parameter');

class plgContentJHGallery extends JPlugin
{

    // JoomlaWorks reference parameters
    public $plg_name             = "jhgallery";
    public $plg_tag              = "galeriePrivee";
    public $plg_tag_public       = "galeriePublique";
    public $plg_version          = "0.0.1";

    public function __construct(&$subject, $params)
    {
        parent::__construct($subject, $params);

        // Define the DS constant (b/c)
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }
    }

    public function onContentPrepare($context, &$row, &$params, $page = 0)
    {
        // API
        jimport('joomla.filesystem.file');
        $app = JFactory::getApplication();
        $document  = JFactory::getDocument();

        $tmpl = JRequest::getCmd('tmpl');
        $print = JRequest::getCmd('print');

        // Assign paths
        $sitePath = JPATH_SITE;
        $siteUrl  = JURI::root(true);
		$pluginLivePath = $siteUrl.'/plugins/content/'.$this->plg_name.'/'.$this->plg_name;
		$defaultImagePath = 'images';

        // Check if plugin is enabled
        if (JPluginHelper::isEnabled('content', $this->plg_name) == false) {
            return;
        }

        // Bail out if the page format is not what we want
        $allowedFormats = array('', 'html', 'feed', 'json');
        if (!in_array(JRequest::getCmd('format'), $allowedFormats)) {
            return;
        }

        // Simple performance check to determine whether plugin should process further
        if (JString::strpos($row->text, $this->plg_tag) === false && JString::strpos($row->text, $this->plg_tag_public) === false) {
            return;
        }

        // expression to search for
        $regex = "#{".$this->plg_tag."}(.*?){/".$this->plg_tag."}#is";
        $regexPublic = "#{".$this->plg_tag_public."}(.*?){/".$this->plg_tag_public."}#is";

        // Find all instances of the plugin and put them in $matches
        preg_match_all($regex, $row->text, $matches);
        preg_match_all($regexPublic, $row->text, $matchesPublic);

        // Number of plugins
        $count = count($matches[0]);
        $countPublic = count($matchesPublic[0]);

        // Plugin only processes if there are any instances of the plugin in the text
        if (!$count && !$countPublic) {
            return;
        }

        // Load the plugin language file the proper way
        JPlugin::loadLanguage('plg_content_'.$this->plg_name, JPATH_ADMINISTRATOR);

        // Check for basic requirements
        if (!extension_loaded('gd') && !function_exists('gd_info')) {
            JError::raiseNotice('', JText::_('PLG_JHGALLERY_NOTICE_01'));
            return;
        }
        if (!is_writable($sitePath.'/cache')) {
            JError::raiseNotice('', JText::_('PLG_JHGALLERY_NOTICE_02'));
            return;
        }

        // ----------------------------------- Get plugin parameters -----------------------------------

        // Get plugin info
        $plugin = JPluginHelper::getPlugin('content', $this->plg_name);

        // Control external parameters and set variable for controlling plugin layout within modules
        if (!$params) {
            $params = class_exists('JParameter') ? new JParameter(null) : new JRegistry(null);
        }
        if (is_string($params)) {
            $params = class_exists('JParameter') ? new JParameter($params) : new JRegistry($params);
        }
        $parsedInModule = $params->get('parsedInModule');

        $pluginParams = class_exists('JParameter') ? new JParameter($plugin->params) : new JRegistry($plugin->params);

        $galleries_rootfolder = ($params->get('galleries_rootfolder')) ? $params->get('galleries_rootfolder') : $pluginParams->get('galleries_rootfolder', $defaultImagePath);
        $popup_engine = 'jquery_fancybox';
        $jQueryHandling = $pluginParams->get('jQueryHandling', '1.12.4');
        $thb_template = 'Classic';
        $thb_width = (!is_null($params->get('thb_width', null))) ? $params->get('thb_width') : $pluginParams->get('thb_width', 200);
        $thb_height = (!is_null($params->get('thb_height', null))) ? $params->get('thb_height') : $pluginParams->get('thb_height', 160);
        $smartResize = 1;
        $jpg_quality = $pluginParams->get('jpg_quality', 80);
        $showcaptions = 0;
        $cache_expire_time = $pluginParams->get('cache_expire_time', 3600) * 60; // Cache expiration time in minutes
        // Advanced
        $memoryLimit = (int)$pluginParams->get('memoryLimit');
        if ($memoryLimit) {        $this->renderJHGallery($row, $params, $page = 0);
            ini_set("memory_limit", $memoryLimit."M");
        }

        // Cleanups
        // Remove first and last slash if they exist
        if (substr($galleries_rootfolder, 0, 1) == '/') {
            $galleries_rootfolder = substr($galleries_rootfolder, 1);
        }
        if (substr($galleries_rootfolder, -1, 1) == '/') {
            $galleries_rootfolder = substr($galleries_rootfolder, 0, -1);
        }

        // Includes
        require_once dirname(__FILE__).'/'.$this->plg_name.'/includes/helper.php';

        // Other assignments
        $transparent = $pluginLivePath.'/includes/images/transparent.gif';

        // When used with K2 extra fields
        if (!isset($row->title)) {
            $row->title = '';
        }

        // ----------------------------------- Prepare the output -----------------------------------

        // Process plugin tags
        if (preg_match_all($regex, $row->text, $matches, PREG_PATTERN_ORDER) > 0) {

            // start the replace loop
            foreach ($matches[0] as $key => $match) {
                $tagcontent = preg_replace("/{.+?}/", "", $match);
                $tagcontent = str_replace(array('"','\'','`'), array('&quot;','&apos;','&#x60;'), $tagcontent); // Address potential XSS attacks
                $tagcontent = trim(strip_tags($tagcontent));

                if (strpos($tagcontent, ':')!==false) {
                    $tagparams        = explode(':', $tagcontent);
                    $galleryFolder    = $tagparams[0];
                } else {
                    $galleryFolder    = $tagcontent;
                }

                // HTML & CSS assignments
                $srcimgfolder = $galleries_rootfolder.'/'.$galleryFolder;
                $gal_id = substr(md5($key.$srcimgfolder), 1, 10);

                // Render the gallery
                $gallery = JHGalleryHelper::renderGallery($srcimgfolder, $thb_width, $thb_height, $smartResize, $jpg_quality, $cache_expire_time, $gal_id, false);

                if (!$gallery) {
                    JError::raiseNotice('', JText::_('PLG_JHGALLERY_NOTICE_03').' '.$srcimgfolder);
                    continue;
                }

                // CSS & JS includes: Append head includes, but not when we're outputing raw content (like in K2)
                if (JRequest::getCmd('format') == '' || JRequest::getCmd('format') == 'html') {

                    // Initiate variables
                    $relName = '';
                    $extraClass = '';
                    $extraWrapperClass = '';
                    $legacyHeadIncludes = '';
                    $customLinkAttributes = '';

                    $popupPath = "{$pluginLivePath}/includes/js/{$popup_engine}";
                    $popupRequire = dirname(__FILE__).'/'.$this->plg_name.'/includes/js/'.$popup_engine.'/popup.php';

                    if (file_exists($popupRequire) && is_readable($popupRequire)) {
                        require $popupRequire;
                    }

                    JHtml::_('behavior.framework');

                    if (count($stylesheets)) {
                        foreach ($stylesheets as $stylesheet) {
                            if (substr($stylesheet, 0, 4) == 'http' || substr($stylesheet, 0, 2) == '//') {
                                $document->addStyleSheet($stylesheet);
                            } else {
                                $document->addStyleSheet($popupPath.'/'.$stylesheet);
                            }
                        }
                    }
                    if (count($stylesheetDeclarations)) {
                        foreach ($stylesheetDeclarations as $stylesheetDeclaration) {
                            $document->addStyleDeclaration($stylesheetDeclaration);
                        }
                    }

                    if (strpos($popup_engine, 'jquery_') !== false && $jQueryHandling != 0) {
                        JHtml::_('jquery.framework');
                    }

                    if (count($scripts)) {
                        foreach ($scripts as $script) {
                            if (substr($script, 0, 4) == 'http' || substr($script, 0, 2) == '//') {
                                $document->addScript($script);
                            } else {
                                $document->addScript($popupPath.'/'.$script);
                            }
                        }
                    }
                    if (count($scriptDeclarations)) {
                        foreach ($scriptDeclarations as $scriptDeclaration) {
                            $document->addScriptDeclaration($scriptDeclaration);
                        }
                    }

                    if ($legacyHeadIncludes) {
                        $document->addCustomTag($legacyHeadIncludes);
                    }

                    if ($extraClass) {
                        $extraClass = ' '.$extraClass;
                    }

                    if ($extraWrapperClass) {
                        $extraWrapperClass = ' '.$extraWrapperClass;
                    }

                    if ($customLinkAttributes) {
                        $customLinkAttributes = ' '.$customLinkAttributes;
                    }

                    $pluginCSS = JHGalleryHelper::getTemplatePath($this->plg_name, 'css/template.css', $thb_template);
                    $pluginCSS = $pluginCSS->http;
                    $document->addStyleSheet($pluginCSS.'?v='.$this->plg_version);
                }

                // Print output
                $isPrintPage = ($tmpl == "component" && $print !== false) ? true : false;

                // Fetch the template
                ob_start();
                $templatePath = JHGalleryHelper::getTemplatePath($this->plg_name, 'default.php', $thb_template);
                $templatePath = $templatePath->file;
                include $templatePath;
                $getTemplate = ob_get_contents();
                ob_end_clean();

                // Output
                $plg_html = $getTemplate;

                // Do the replace
                $row->text = preg_replace("#{".$this->plg_tag."}".preg_quote($tagcontent)."{/".$this->plg_tag."}#s", $plg_html, $row->text);
            } // end foreach

            // Global head includes
            if (JRequest::getCmd('format') == '' || JRequest::getCmd('format') == 'html') {
                $document->addScript($pluginLivePath.'/includes/js/behaviour.js?v='.$this->plg_version);
            }
        } // end if
        
        // Process plugin tags
        if (preg_match_all($regexPublic, $row->text, $matchesPublic, PREG_PATTERN_ORDER) > 0) {

            // start the replace loop
            foreach ($matchesPublic[0] as $key => $match) {
                $tagcontent = preg_replace("/{.+?}/", "", $match);
                $tagcontent = str_replace(array('"','\'','`'), array('&quot;','&apos;','&#x60;'), $tagcontent); // Address potential XSS attacks
                $tagcontent = trim(strip_tags($tagcontent));

                if (strpos($tagcontent, ':')!==false) {
                    $tagparams        = explode(':', $tagcontent);
                    $galleryFolder    = $tagparams[0];
                } else {
                    $galleryFolder    = $tagcontent;
                }

                // HTML & CSS assignments
                $srcimgfolder = $galleries_rootfolder.'/'.$galleryFolder;
                $gal_id = substr(md5($key.$srcimgfolder), 1, 10);

                // Render the gallery
                $gallery = JHGalleryHelper::renderGallery($srcimgfolder, $thb_width, $thb_height, $smartResize, $jpg_quality, $cache_expire_time, $gal_id, true);

                if (!$gallery) {
                    JError::raiseNotice('', JText::_('PLG_JHGALLERY_NOTICE_03').' '.$srcimgfolder);
                    continue;
                }

                // CSS & JS includes: Append head includes, but not when we're outputing raw content (like in K2)
                if (JRequest::getCmd('format') == '' || JRequest::getCmd('format') == 'html') {

                    // Initiate variables
                    $relName = '';
                    $extraClass = '';
                    $extraWrapperClass = '';
                    $legacyHeadIncludes = '';
                    $customLinkAttributes = '';

                    $popupPath = "{$pluginLivePath}/includes/js/{$popup_engine}";
                    $popupRequire = dirname(__FILE__).'/'.$this->plg_name.'/includes/js/'.$popup_engine.'/popup.php';

                    if (file_exists($popupRequire) && is_readable($popupRequire)) {
                        require $popupRequire;
                    }

                    JHtml::_('behavior.framework');

                    if (count($stylesheets)) {
                        foreach ($stylesheets as $stylesheet) {
                            if (substr($stylesheet, 0, 4) == 'http' || substr($stylesheet, 0, 2) == '//') {
                                $document->addStyleSheet($stylesheet);
                            } else {
                                $document->addStyleSheet($popupPath.'/'.$stylesheet);
                            }
                        }
                    }
                    if (count($stylesheetDeclarations)) {
                        foreach ($stylesheetDeclarations as $stylesheetDeclaration) {
                            $document->addStyleDeclaration($stylesheetDeclaration);
                        }
                    }

                    if (strpos($popup_engine, 'jquery_') !== false && $jQueryHandling != 0) {
                        JHtml::_('jquery.framework');
                    }

                    if (count($scripts)) {
                        foreach ($scripts as $script) {
                            if (substr($script, 0, 4) == 'http' || substr($script, 0, 2) == '//') {
                                $document->addScript($script);
                            } else {
                                $document->addScript($popupPath.'/'.$script);
                            }
                        }
                    }
                    if (count($scriptDeclarations)) {
                        foreach ($scriptDeclarations as $scriptDeclaration) {
                            $document->addScriptDeclaration($scriptDeclaration);
                        }
                    }

                    if ($legacyHeadIncludes) {
                        $document->addCustomTag($legacyHeadIncludes);
                    }

                    if ($extraClass) {
                        $extraClass = ' '.$extraClass;
                    }

                    if ($extraWrapperClass) {
                        $extraWrapperClass = ' '.$extraWrapperClass;
                    }

                    if ($customLinkAttributes) {
                        $customLinkAttributes = ' '.$customLinkAttributes;
                    }

                    $pluginCSS = JHGalleryHelper::getTemplatePath($this->plg_name, 'css/template.css', $thb_template);
                    $pluginCSS = $pluginCSS->http;
                    $document->addStyleSheet($pluginCSS.'?v='.$this->plg_version);
                }

                // Print output
                $isPrintPage = ($tmpl == "component" && $print !== false) ? true : false;

                // Fetch the template
                ob_start();
                $templatePath = JHGalleryHelper::getTemplatePath($this->plg_name, 'default.php', $thb_template);
                $templatePath = $templatePath->file;
                include $templatePath;
                $getTemplate = ob_get_contents();
                ob_end_clean();

                // Output
                $plg_html = $getTemplate;

                // Do the replace
                $row->text = preg_replace("#{".$this->plg_tag_public."}".preg_quote($tagcontent)."{/".$this->plg_tag_public."}#s", $plg_html, $row->text);
            } // end foreach

            // Global head includes
            if (JRequest::getCmd('format') == '' || JRequest::getCmd('format') == 'html') {
                $document->addScript($pluginLivePath.'/includes/js/behaviour.js?v='.$this->plg_version);
            }
        } // end if
    } // END FUNCTION
} // END CLASS
