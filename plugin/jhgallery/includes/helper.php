<?php

// no direct access
defined('_JEXEC') or die('Restricted access');

class JHGalleryHelper
{
    public static function renderGallery($srcimgfolder, $thb_width, $thb_height, $smartResize, $jpg_quality, $cache_expire_time, $gal_id, $public)
    {
        // API
        jimport('joomla.filesystem.folder');

        // Path assignment
        $sitePath = JPATH_SITE.'/';
        if (JRequest::getCmd('format')=='feed') {
            $siteUrl = JURI::root(true).'';
        } else {
            $siteUrl = JURI::root(true).'/';
        }

        // Internal parameters
        $prefix = "jhgallery_cache_";

        // Set the cache folder
        if ($public) {
			$cacheFolderPath = JPATH_SITE.'/cache/jhgallery';
		} else {
			$cacheFolderPath = JPATH_SITE.'/cache/jhprivategallery';
		}
        if (file_exists($cacheFolderPath) && is_dir($cacheFolderPath)) {
            // all OK
        } else {
            mkdir($cacheFolderPath);
        }

        // Check if the source folder exists and read it
        $srcFolder = JFolder::files($sitePath.$srcimgfolder);

        // Proceed if the folder is OK or fail silently
        if (!$srcFolder) {
            return;
        }

        // Loop through the source folder for images
        $fileTypes = array('jpg', 'jpeg', 'gif', 'png');
        // Create an array of file types
        $found = array();
        // Create an array for matching files
        foreach ($srcFolder as $srcImage) {
            $fileInfo = pathinfo($srcImage);
            if (array_key_exists('extension', $fileInfo) && in_array(strtolower($fileInfo['extension']), $fileTypes)) {
                $found[] = $srcImage;
            }
        }

        // Bail out if there are no images found
        if (count($found) == 0) {
            return;
        }

        // Sort array
        sort($found);

        // Initiate array to hold gallery
        $gallery = array();

        // Loop through the image file list
        foreach ($found as $key => $filename) {

            // Determine thumb image filename
            if (strtolower(substr($filename, -4, 4)) == 'jpeg') {
                $thumbfilename = substr($filename, 0, -4).'jpg';
            } elseif (strtolower(substr($filename, -3, 3)) == 'gif' || strtolower(substr($filename, -3, 3)) == 'png' || strtolower(substr($filename, -3, 3)) == 'jpg') {
                $thumbfilename = substr($filename, 0, -3).'jpg';
            }

            // Object to hold each image elements
            $gallery[$key] = new JObject;

            // Assign source image and path to a variable
            $original = $sitePath.$srcimgfolder.'/'.$filename;

            // Check if thumb image exists already
            $thumbimage = $cacheFolderPath.'/'.$prefix.$gal_id.'_'.strtolower(self::cleanThumbName($thumbfilename));

            if (file_exists($thumbimage) && is_readable($thumbimage) && (filemtime($thumbimage) + $cache_expire_time) > time()) {
                // do nothing
            } else {
                // Otherwise create the thumb image

                // begin by getting the details of the original
                list($width, $height, $type) = getimagesize($original);

                // create an image resource for the original
                switch ($type) {
                    case 1:
                        $source = @ imagecreatefromgif($original);
                        if (!$source) {
                            JError::raiseNotice('', JText::_('PLG_JHGALLERY_ERROR_GIFS'));
                            return;
                        }
                        break;
                    case 2:
                        $source = imagecreatefromjpeg($original);
                        break;
                    case 3:
                        $source = imagecreatefrompng($original);
                        break;
                    default:
                        $source = null;
                }

                // Bail out if the image resource is not OK
                if (!$source) {
                    JError::raiseNotice('', JText::_('PLG_JHGALLERY_ERROR_SRC_IMGS'));
                    return;
                }

                // calculate thumbnails
                $thumbnail = self::thumbDimCalc($width, $height, $thb_width, $thb_height, $smartResize);

                $thumb_width = $thumbnail['width'];
                $thumb_height = $thumbnail['height'];

                // create an image resource for the thumbnail
                $thumb = imagecreatetruecolor($thumb_width, $thumb_height);

                // create the resized copy
                imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);

                // convert and save all thumbs to .jpg
                $success = imagejpeg($thumb, $thumbimage, $jpg_quality);

                // Bail out if there is a problem in the GD conversion
                if (!$success) {
                    return;
                }

                // remove the image resources from memory
                imagedestroy($source);
                imagedestroy($thumb);
            }

            // Assemble the image elements
            $gallery[$key]->filename = $filename;
            $gallery[$key]->sourceImageFilePath = $siteUrl.$srcimgfolder.'/'.self::replaceWhiteSpace($filename);
            if ($public) {
				$gallery[$key]->thumbImageFilePath = $siteUrl.'cache/jhgallery/'.$prefix.$gal_id.'_'.strtolower(self::cleanThumbName($thumbfilename));
            } else {
				$gallery[$key]->thumbImageFilePath = $siteUrl.'cache/jhprivategallery/'.$prefix.$gal_id.'_'.strtolower(self::cleanThumbName($thumbfilename));
			}
			$gallery[$key]->width = $thb_width;
            $gallery[$key]->height = $thb_height;
        }// foreach loop

        // OUTPUT
        return $gallery;
    }



    /* ------------------ Helper Functions ------------------ */

    // Calculate thumbnail dimensions
    public static function thumbDimCalc($width, $height, $thb_width, $thb_height, $smartResize)
    {
        if ($smartResize) {
            // thumb ratio bigger that container ratio
            if ($width / $height > $thb_width / $thb_height) {
                // wide containers
                if ($thb_width >= $thb_height) {
                    // wide thumbs
                    if ($width > $height) {
                        $thumb_width = $thb_height * $width / $height;
                        $thumb_height = $thb_height;
                    }
                    // high thumbs
                    else {
                        $thumb_width = $thb_height * $width / $height;
                        $thumb_height = $thb_height;
                    }
                    // high containers
                } else {
                    // wide thumbs
                    if ($width > $height) {
                        $thumb_width = $thb_height * $width / $height;
                        $thumb_height = $thb_height;
                    }
                    // high thumbs
                    else {
                        $thumb_width = $thb_height * $width / $height;
                        $thumb_height = $thb_height;
                    }
                }
            } else {
                // wide containers
                if ($thb_width >= $thb_height) {
                    // wide thumbs
                    if ($width > $height) {
                        $thumb_width = $thb_width;
                        $thumb_height = $thb_width * $height / $width;
                    }
                    // high thumbs
                    else {
                        $thumb_width = $thb_width;
                        $thumb_height = $thb_width * $height / $width;
                    }
                    // high containers
                } else {
                    // wide thumbs
                    if ($width > $height) {
                        $thumb_width = $thb_height * $width / $height;
                        $thumb_height = $thb_height;
                    }
                    // high thumbs
                    else {
                        $thumb_width = $thb_width;
                        $thumb_height = $thb_width * $height / $width;
                    }
                }
            }
        } else {
            if ($width > $height) {
                $thumb_width = $thb_width;
                $thumb_height = $thb_width * $height / $width;
            } elseif ($width < $height) {
                $thumb_width = $thb_height * $width / $height;
                $thumb_height = $thb_height;
            } else {
                $thumb_width = $thb_width;
                $thumb_height = $thb_height;
            }
        }

        $thumbnail = array();
        $thumbnail['width'] = round($thumb_width);
        $thumbnail['height'] = round($thumb_height);

        return $thumbnail;
    }

    // Path overrides
    public static function getTemplatePath($pluginName, $file, $tmpl)
    {
        $app = JFactory::getApplication();
        $p = new JObject;
        $pluginGroup = 'content';

        $jTemplate = $app->getTemplate();

        if ($app->isAdmin()) {
            $db = JFactory::getDBO();
            if (version_compare(JVERSION, '1.6', 'ge')) {
                $query = "SELECT template FROM #__template_styles WHERE client_id = 0 AND home = 1";
            } else {
                $query = "SELECT template FROM #__templates_menu WHERE client_id = 0 AND menuid = 0";
            }
            $db->setQuery($query);
            $jTemplate = $db->loadResult();
        }

        if (file_exists(JPATH_SITE.'/templates/'.$jTemplate.'/html/'.$pluginName.'/'.$tmpl.'/'.$file)) {
            $p->file = JPATH_SITE.'/templates/'.$jTemplate.'/html/'.$pluginName.'/'.$tmpl.'/'.$file;
            $p->http = JURI::root(true)."/templates/".$jTemplate."/html/{$pluginName}/{$tmpl}/{$file}";
        } else {
            if (version_compare(JVERSION, '1.6.0', 'ge')) {
                // Joomla 1.6+
                $p->file = JPATH_SITE.'/plugins/'.$pluginGroup.'/'.$pluginName.'/'.$pluginName.'/tmpl/'.$tmpl.'/'.$file;
                $p->http = JURI::root(true)."/plugins/{$pluginGroup}/{$pluginName}/{$pluginName}/tmpl/{$tmpl}/{$file}";
            } else {
                // Joomla 1.5
                $p->file = JPATH_SITE.'/plugins/'.$pluginGroup.'/'.$pluginName.'/tmpl/'.$tmpl.'/'.$file;
                $p->http = JURI::root(true)."/plugins/{$pluginGroup}/{$pluginName}/tmpl/{$tmpl}/{$file}";
            }
        }
        return $p;
    }

    // Replace white space
    public static function replaceWhiteSpace($text_to_parse)
    {
        $source_html = array(" ");
        $replacement_html = array("%20");
        return str_replace($source_html, $replacement_html, $text_to_parse);
    }

    // Cleanup thumbnail filenames
    public static function cleanThumbName($text_to_parse)
    {
        $source_html = array(' ', ',');
        $replacement_html = array('_', '_');
        return str_replace($source_html, $replacement_html, $text_to_parse);
    }
} // End class
