<?php

if ($_SESSION['RF']["verify"] != "RESPONSIVEfilemanager")
{
	die('forbiden');
}

require_once( dirname(__FILE__) . '/php_image_magician.php' );
require_once( dirname(__FILE__) . '/Response.php' );
require_once( dirname(__FILE__) . '/s3.php' );

if ( ! function_exists('response'))
{
	/**
	 * Response construction helper
	 *
	 * @param string $content
	 * @param int    $statusCode
	 * @param array  $headers
	 *
	 * @return \Response|\Illuminate\Http\Response
	 */
	function response($content = '', $statusCode = 200, $headers = array())
	{
		$responseClass = class_exists('Illuminate\Http\Response') ? '\Illuminate\Http\Response' : 'Response';

		return new $responseClass($content, $statusCode, $headers);
	}
}

if ( ! function_exists('trans'))
{
	// language
	if ( ! isset($_SESSION['RF']['language'])
		|| file_exists($_SESSION['RF']['language_file']) === false
		|| ! is_readable($_SESSION['RF']['language_file'])
	)
	{
		if ( !isset($default_language) )
		{
			$default_language = 'en_EN';
		}
		$lang = $default_language;

		if (isset($_GET['lang']) && $_GET['lang'] != 'undefined' && $_GET['lang'] != '')
		{
			$lang = fix_get_params($_GET['lang']);
			$lang = trim($lang);
		}

		$language_file = dirname(dirname(__FILE__)) . '/lang/' . $default_language . '.php';
		if ($lang != $default_language)
		{
			$path_parts = pathinfo($lang);

			if (is_readable(dirname(dirname(__FILE__)) . '/lang/' . $path_parts['basename'] . '.php'))
			{
				$language_file = dirname(dirname(__FILE__)) . '/lang/' . $path_parts['basename'] . '.php';
			}
			else
			{
				echo "<script>console.log('The " . $lang . " language file is not readable! Falling back...');</script>";
			}
		}

		// add lang file to session for easy include
		$_SESSION['RF']['language'] = $lang;
		$_SESSION['RF']['language_file'] = $language_file;
	}
	else
	{
		$lang = $_SESSION['RF']['language'];
		$language_file = $_SESSION['RF']['language_file'];
	}

	$lang_vars = include $language_file;

	if ( ! is_array($lang_vars))
	{
		$lang_vars = array();
	}
	/**
	 * Translate language variable
	 *
	 * @param $var string name
	 *
	 * @return string translated variable
	 */
	function trans($var)
	{
		global $lang_vars;

		return (array_key_exists($var, $lang_vars)) ? $lang_vars[ $var ] : $var;
	}
}

/**
 * Check if the user can access the path.
 * Notice that it will overwrite the permissions configuration in config/config.php.
 * @param   path    The path
 * @return  Accessible or not
 */
function access_check( $path )
{
	global $current_path, $create_folders, $create_text_files, $copy_cut_dirs, $copy_cut_files,
		$delete_files, $delete_folders, $duplicate_files, $edit_text_files,
		$rename_files, $rename_folders, $upload_files;
	$path = substr( $path, strlen($current_path) );
	$rights = array();
	$accessible = TRUE;

	if ( isset($_SESSION['admin']['user_rights']) && $path !== '' )
	{
		// Shared
		if ( preg_match('#^webpage/shared/#', $path) )
		{
			$rights = $_SESSION['admin']['user_rights']['share_file_admin'];
		}

		// Page specific
		else if ( preg_match('#^webpage/page/#', $path) )
		{
			$rights = $_SESSION['admin']['user_rights']['file_admin'];
			$accessible = in_array(
				str_replace( '_', '/', explode('/', $path)[4] ),
				$_SESSION['admin']['user_accessible_locales']
			);
		}

		// Announcement
		else if ( preg_match('#^announcement/#', $path) )
		{
			$rights = $_SESSION['admin']['user_rights']['announcement_admin'];
		}

		// Offer
		else if ( preg_match('#^offer/#', $path) )
		{
			$rights = $_SESSION['admin']['user_rights']['offer_admin'];
		}
		
		// Others
		else
		{
			$module = current( explode('/', $path) ) . '_admin';
			$rights = $_SESSION['admin']['user_rights'][$module];
		}
	}

	// Check edit (3) rights
	$create_folders = $create_text_files = $copy_cut_dirs = $copy_cut_files
		= $delete_files = $delete_folders = $duplicate_files = $edit_text_files
		= $rename_files = $rename_folders = $upload_files
		= in_array( 3, $rights ) && $accessible;

	// Check access (0) and edit (1) rights
	return in_array( 0, $rights ) && in_array( 1, $rights ) && $accessible;
}

/**
 * Delete directory
 *
 * @param  string  $dir
 *
 * @return  bool
 */
function deleteDir($dir)
{
	if ( ! file_exists($dir))
	{
		return true;
	}
	if ( ! is_dir($dir))
	{
		return unlink($dir);
	}
	foreach (scandir($dir) as $item)
	{
		if ($item == '.' || $item == '..')
		{
			continue;
		}
		if ( ! deleteDir($dir . DIRECTORY_SEPARATOR . $item))
		{
			return false;
		}
	}

	return rmdir($dir);
}

/**
 * Make a file copy
 *
 * @param  string  $old_path
 * @param  string  $name      New file name without extension
 *
 * @return  bool
 */
function duplicate_file($old_path, $name)
{
	global $file_exists, $copy;
	if ($file_exists($old_path))
	{
		$info = pathinfo($old_path);
		$new_path = $info['dirname'] . "/" . $name . "." . $info['extension'];
		if ($file_exists($new_path) && $old_path == $new_path)
		{
			return false;
		}

		return $copy($old_path, $new_path);
	}
}

/**
 * Rename file
 *
 * @param  string  $old_path         File to rename
 * @param  string  $name             New file name without extension
 * @param  bool    $transliteration
 *
 * @return bool
 */
function rename_file($old_path, $name, $transliteration)
{
	global $file_exists, $rename;
	$name = fix_filename($name, $transliteration);
	if ($file_exists($old_path))
	{
		$info = pathinfo($old_path);
		$new_path = $info['dirname'] . "/" . $name . "." . $info['extension'];
		if ($file_exists($new_path) && $old_path == $new_path)
		{
			return false;
		}

		return $rename($old_path, $new_path);
	}
}

/**
 * Rename directory
 *
 * @param  string  $old_path         Directory to rename
 * @param  string  $name             New directory name
 * @param  bool    $transliteration
 *
 * @return bool
 */
function rename_folder($old_path, $name, $transliteration)
{
	global $file_exists, $rename;
	$old_path = rtrim( $old_path, '/' ) . '/';
	$name = fix_filename($name, $transliteration, false, '_', true);
	if ($file_exists($old_path))
	{
		$new_path = fix_dirname($old_path) . '/' . $name . '/';
		if ($file_exists($new_path) && $old_path == $new_path)
		{
			return false;
		}

		return $rename($old_path, $new_path);
	}
}

/**
 * Create new image from existing file
 *
 * @param  string  $imgfile    Source image file name
 * @param  string  $imgthumb   Thumbnail file name
 * @param  int     $newwidth   Thumbnail width
 * @param  int     $newheight  Optional thumbnail height
 * @param  string  $option     Type of resize
 *
 * @return bool
 * @throws \Exception
 */
function create_img($imgfile, $imgthumb, $newwidth, $newheight = null, $option = "crop")
{
	global $file_exists, $file_get_contents;
	$result = false;
	if($file_exists($imgfile) || strpos($imgfile,'http')===0){
		$timeLimit = ini_get('max_execution_time');
		set_time_limit(30);
		$imgcontent = $file_get_contents($imgfile);
		if (strpos($imgfile,'http')===0 || image_check_memory_usage($imgcontent, $newwidth, $newheight))
		{
			$imgtemp = sys_get_temp_dir() . '/' . basename($imgfile);
			file_put_contents($imgtemp, $imgcontent);
			try
			{
				$magicianObj = new imageLib($imgtemp);
				$magicianObj->resizeImage($newwidth, $newheight, $option);
				$magicianObj->saveImage($imgthumb, 80);
				$result = true;
			}
			catch ( Exception $e )
			{
			}
			unlink($imgtemp);
		}
		set_time_limit($timeLimit);
	}
	return $result;
}

/**
 * Convert convert size in bytes to human readable
 *
 * @param  int  $size
 *
 * @return  string
 */
function makeSize($size)
{
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	$u = 0;
	while ((round($size / 1024) > 0) && ($u < 4))
	{
		$size = $size / 1024;
		$u++;
	}

	return (number_format($size, 0) . " " . $units[ $u ]);
}

/**
 * Determine directory size
 *
 * @param  string  $path
 *
 * @return  int
 */
function folder_info($path)
{
	global $function_prefix, $scandir, $is_dir, $filesize;
	$total_size = 0;
	$files = $scandir($path);
	$cleanPath = rtrim($path, '/') . '/';
	$files_count = 0;
	$folders_count = 0;
	foreach ($files as $t)
	{
		if ($t != "." && $t != "..")
		{
			$currentFile = $cleanPath . $t;
			$metadata = $function_prefix == 's3_' ? s3_head_object($currentFile) : $currentFile;
			if ($is_dir($currentFile))
			{
				list($size,$tmp,$tmp1) = folder_info($currentFile);
				$total_size += $size;
				$folders_count ++;
			}
			else
			{
				$size = $filesize($metadata);
				$total_size += $size;
				$files_count++;
			}
		}
	}

	return array($total_size,$files_count,$folders_count);
}

/**
 * Get number of files in a directory
 *
 * @param  string  $path
 *
 * @return  int
 */
function filescount($path)
{
	$total_count = 0;
	$files = scandir($path);
	$cleanPath = rtrim($path, '/') . '/';

	foreach ($files as $t)
	{
		if ($t != "." && $t != "..")
		{
			$currentFile = $cleanPath . $t;
			if (is_dir($currentFile))
			{
				$size = filescount($currentFile);
				$total_count += $size;
			}
			else
			{
				$total_count += 1;
			}
		}
	}

	return $total_count;
}

/**
 * Create directory for images and/or thumbnails
 *
 * @param  string  $path
 * @param  string  $path_thumbs
 */
function create_folder($path = null, $path_thumbs = null)
{
	global $file_exists, $mkdir;
	$oldumask = umask(0);
	if ($path) $path = rtrim($path, '/') . '/';
	if ($path && ! $file_exists($path))
	{
		$mkdir($path);
	} // or even 01777 so you get the sticky bit set
	if ($path_thumbs && ! file_exists($path_thumbs))
	{
		mkdir($path_thumbs, 0755, true) or die("$path_thumbs cannot be found");
	} // or even 01777 so you get the sticky bit set
	umask($oldumask);
}

/**
 * Get file extension present in directory
 *
 * @param  string  $path
 * @param  string  $ext
 */
function check_files_extensions_on_path($path, $ext)
{
	if ( ! is_dir($path))
	{
		$fileinfo = pathinfo($path);
		if ( ! in_array(mb_strtolower($fileinfo['extension']), $ext))
		{
			unlink($path);
		}
	}
	else
	{
		$files = scandir($path);
		foreach ($files as $file)
		{
			check_files_extensions_on_path(trim($path, '/') . "/" . $file, $ext);
		}
	}
}

/**
 * Get file extension present in PHAR file
 *
 * @param  string  $phar
 * @param  array   $files
 * @param  string  $basepath
 * @param  string  $ext
 */
function check_files_extensions_on_phar($phar, &$files, $basepath, $ext)
{
	foreach ($phar as $file)
	{
		if ($file->isFile())
		{
			if (in_array(mb_strtolower($file->getExtension()), $ext))
			{
				$files[] = $basepath . $file->getFileName();
			}
		}
		else
		{
			if ($file->isDir())
			{
				$iterator = new DirectoryIterator($file);
				check_files_extensions_on_phar($iterator, $files, $basepath . $file->getFileName() . '/', $ext);
			}
		}
	}
}

/**
 * Cleanup input
 *
 * @param  string  $str
 *
 * @return  string
 */
function fix_get_params($str)
{
	return strip_tags(preg_replace("/[^a-zA-Z0-9\.\[\]_| -]/", '', $str));
}

/**
 * Cleanup filename
 *
 * @param  string  $str
 * @param  bool    $transliteration
 * @param  bool    $convert_spaces
 * @param  string  $replace_with
 * @param  bool    $is_folder
 *
 * @return string
 */
function fix_filename($str, $transliteration, $convert_spaces = false, $replace_with = "_", $is_folder = false)
{
	if ($convert_spaces)
	{
		$str = str_replace(' ', $replace_with, $str);
	}

	if ($transliteration)
	{
		if (function_exists('transliterator_transliterate'))
		{
			 $str = transliterator_transliterate('Accents-Any', utf8_encode($str));
		}
		else
		{
			$str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
		}

		$str = preg_replace("/[^a-zA-Z0-9\.\[\]_| -]/", '', $str);
	}

	$str = str_replace(array( '"', "'", "/", "\\" ), "", $str);
	$str = strip_tags($str);

	// Empty or incorrectly transliterated filename.
	// Here is a point: a good file UNKNOWN_LANGUAGE.jpg could become .jpg in previous code.
	// So we add that default 'file' name to fix that issue.
	if (strpos($str, '.') === 0 && $is_folder === false)
	{
		$str = 'file' . $str;
	}

	return trim($str);
}

/**
 * Cleanup directory name
 *
 * @param  string  $str
 *
 * @return  string
 */
function fix_dirname($str)
{
	return str_replace('~', ' ', dirname(str_replace(' ', '~', $str)));
}

/**
 * Correct strtoupper handling
 *
 * @param  string  $str
 *
 * @return  string
 */
function fix_strtoupper($str)
{
	if (function_exists('mb_strtoupper'))
	{
		return mb_strtoupper($str);
	}
	else
	{
		return strtoupper($str);
	}
}

/**
 * Correct strtolower handling
 *
 * @param  string  $str
 *
 * @return  string
 */
function fix_strtolower($str)
{
	if (function_exists('mb_strtoupper'))
	{
		return mb_strtolower($str);
	}
	else
	{
		return strtolower($str);
	}
}

function fix_path($path, $transliteration, $convert_spaces = false, $replace_with = "_")
{
	$info = pathinfo($path);
	$tmp_path = $info['dirname'];
	$str = fix_filename($info['filename'], $transliteration, $convert_spaces, $replace_with);
	if ($tmp_path != "")
	{
		return $tmp_path . '/' . $str;
	}
	else
	{
		return $str;
	}
}

/**
 * Get current base url
 *
 * @return  string
 */
function base_url()
{
	return sprintf(
		"%s://%s",
		isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		$_SERVER['HTTP_HOST']
	);
}

/**
 * @param  $current_path
 * @param  $fld
 *
 * @return  bool
 */
function config_loading($current_path, $fld)
{
	if (file_exists($current_path . $fld . ".config"))
	{
		require_once($current_path . $fld . ".config");

		return true;
	}
	echo "!!!!" . $parent = fix_dirname($fld);
	if ($parent != "." && ! empty($parent))
	{
		config_loading($current_path, $parent);
	}

	return false;
}

/**
 * Check if memory is enough to process image
 *
 * @param  string  $img
 * @param  int     $max_breedte
 * @param  int     $max_hoogte
 *
 * @return bool
 */
function image_check_memory_usage($img, $max_breedte, $max_hoogte)
{
	$K64 = 65536; // number of bytes in 64K
	$memory_usage = memory_get_usage();
	$memory_limit = abs(intval(str_replace('M', '', ini_get('memory_limit')) * 1024 * 1024));
	$image_properties = getimagesizefromstring($img);
	$image_width = $image_properties[0];
	$image_height = $image_properties[1];
	if (isset($image_properties['bits']))
		$image_bits = $image_properties['bits'];
	else
		$image_bits = 0;
	$image_memory_usage = $K64 + ($image_width * $image_height * ($image_bits) * 2);
	$thumb_memory_usage = $K64 + ($max_breedte * $max_hoogte * ($image_bits) * 2);
	$memory_needed = intval($memory_usage + $image_memory_usage + $thumb_memory_usage);

	if ($memory_needed > $memory_limit)
	{
		ini_set('memory_limit', (intval($memory_needed / 1024 / 1024) + 5) . 'M');
		if (ini_get('memory_limit') == (intval($memory_needed / 1024 / 1024) + 5) . 'M')
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	else
	{
		return true;
	}
}

/**
 * Check is string is ended with needle
 *
 * @param  string  $haystack
 * @param  string  $needle
 *
 * @return  bool
 */
function endsWith($haystack, $needle)
{
	return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

/**
 * TODO REFACTOR THIS!
 *
 * @param $targetPath
 * @param $targetFile
 * @param $name
 * @param $current_path
 * @param $relative_image_creation
 * @param $relative_path_from_current_pos
 * @param $relative_image_creation_name_to_prepend
 * @param $relative_image_creation_name_to_append
 * @param $relative_image_creation_width
 * @param $relative_image_creation_height
 * @param $relative_image_creation_option
 * @param $fixed_image_creation
 * @param $fixed_path_from_filemanager
 * @param $fixed_image_creation_name_to_prepend
 * @param $fixed_image_creation_to_append
 * @param $fixed_image_creation_width
 * @param $fixed_image_creation_height
 * @param $fixed_image_creation_option
 *
 * @return bool
 */
function new_thumbnails_creation($targetPath, $targetFile, $name, $current_path, $relative_image_creation, $relative_path_from_current_pos, $relative_image_creation_name_to_prepend, $relative_image_creation_name_to_append, $relative_image_creation_width, $relative_image_creation_height, $relative_image_creation_option, $fixed_image_creation, $fixed_path_from_filemanager, $fixed_image_creation_name_to_prepend, $fixed_image_creation_to_append, $fixed_image_creation_width, $fixed_image_creation_height, $fixed_image_creation_option)
{
	//create relative thumbs
	$all_ok = true;
	if ($relative_image_creation)
	{
		foreach ($relative_path_from_current_pos as $k => $path)
		{
			if ($path != "" && $path[ strlen($path) - 1 ] != "/")
			{
				$path .= "/";
			}
			if ( ! file_exists($targetPath . $path))
			{
				create_folder($targetPath . $path, false);
			}
			$info = pathinfo($name);
			if ( ! endsWith($targetPath, $path))
			{
				if ( ! create_img($targetFile, $targetPath . $path . $relative_image_creation_name_to_prepend[ $k ] . $info['filename'] . $relative_image_creation_name_to_append[ $k ] . "." . $info['extension'], $relative_image_creation_width[ $k ], $relative_image_creation_height[ $k ], $relative_image_creation_option[ $k ]))
				{
					$all_ok = false;
				}
			}
		}
	}

	//create fixed thumbs
	if ($fixed_image_creation)
	{
		foreach ($fixed_path_from_filemanager as $k => $path)
		{
			if ($path != "" && $path[ strlen($path) - 1 ] != "/")
			{
				$path .= "/";
			}
			$base_dir = $path . substr_replace($targetPath, '', 0, strlen($current_path));
			if ( ! file_exists($base_dir))
			{
				create_folder($base_dir, false);
			}
			$info = pathinfo($name);
			if ( ! create_img($targetFile, $base_dir . $fixed_image_creation_name_to_prepend[ $k ] . $info['filename'] . $fixed_image_creation_to_append[ $k ] . "." . $info['extension'], $fixed_image_creation_width[ $k ], $fixed_image_creation_height[ $k ], $fixed_image_creation_option[ $k ]))
			{
				$all_ok = false;
			}
		}
	}

	return $all_ok;
}


/**
 * Get a remote file, using whichever mechanism is enabled
 *
 * @param  string  $url
 *
 * @return  bool|mixed|string
 */
function get_file_by_url($url)
{
	if (ini_get('allow_url_fopen'))
	{
		return file_get_contents($url);
	}
	if ( ! function_exists('curl_version'))
	{
		return false;
	}

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $url);

	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}

/**
 * test for dir/file writability properly
 *
 * @param  string  $dir
 *
 * @return  bool
 */
function is_really_writable($dir)
{
	$dir = rtrim($dir, '/');
	// linux, safe off
	if (DIRECTORY_SEPARATOR == '/' && @ini_get("safe_mode") == false)
	{
		return is_writable($dir);
	}

	// Windows, safe ON. (have to write a file :S)
	if (is_dir($dir))
	{
		$dir = $dir . '/' . md5(mt_rand(1, 1000) . mt_rand(1, 1000));

		if (($fp = @fopen($dir, 'ab')) === false)
		{
			return false;
		}

		fclose($fp);
		@chmod($dir, 0755);
		@unlink($dir);

		return true;
	}
	elseif ( ! is_file($dir) || ($fp = @fopen($dir, 'ab')) === false)
	{
		return false;
	}

	fclose($fp);

	return true;
}

/**
 * Check if a function is callable.
 * Some servers disable copy,rename etc.
 *
 * @parm  string  $name
 *
 * @return  bool
 */
function is_function_callable($name)
{
	if (function_exists($name) === false)
	{
		return false;
	}
	$disabled = explode(',', ini_get('disable_functions'));

	return ! in_array($name, $disabled);
}

/**
 * recursivly copies everything
 *
 * @param  string  $source
 * @param  string  $destination
 * @param  bool    $is_rec
 * @param  string  $function_prefix
 */
function rcopy($source, $destination, $is_rec = false, $function_prefix = '')
{
	$copy = $function_prefix . 'copy';
	$file_exists = $function_prefix . 'file_exists';
	$is_dir = $function_prefix . 'is_dir';
	$mkdir = $function_prefix . 'mkdir';
	$scandir = $function_prefix . 'scandir';
	if ($is_dir(rtrim($source, '/') . '/'))
	{
		$source = rtrim($source, '/') . '/';
		$destination = rtrim($destination, '/') . '/';
		if (!$is_rec)
		{
			$pinfo = pathinfo($source);
			$destination .= $pinfo['basename'] . '/';
		}
		if (!$is_dir($destination))
		{
			$mkdir($destination, 0755, true);
		}

		$files = $scandir($source);
		foreach ($files as $file)
		{
			if ($file != "." && $file != "..")
			{
				rcopy($source . $file, $destination . $file, true, $function_prefix);
			}
		}
	}
	else
	{
		if ($file_exists($source))
		{
			if ($is_dir(rtrim($destination, '/') . '/'))
			{
				$pinfo = pathinfo($source);
				$dest2 = rtrim($destination, '/') . '/' . $pinfo['basename'];
			}
			else
			{
				$dest2 = $destination;
			}

			$copy($source, $dest2);
		}
	}
}

/**
 * recursivly renames everything
 *
 * I know copy and rename could be done with just one function
 * but i split the 2 because sometimes rename fails on windows
 * Need more feedback from users and refactor if needed
 *
 * @param  string  $source
 * @param  string  $destination
 * @param  bool    $is_rec
 * @param  string  $function_prefix
 */
function rrename($source, $destination, $is_rec = false, $function_prefix = '')
{
	$rename = $function_prefix . 'rename';
	$deleteDir = $function_prefix . 'deleteDir';
	$file_exists = $function_prefix . 'file_exists';
	$is_dir = $function_prefix . 'is_dir';
	$mkdir = $function_prefix . 'mkdir';
	$scandir = $function_prefix . 'scandir';
	if ($is_dir(rtrim($source, '/') . '/'))
	{
		$source = rtrim($source, '/') . '/';
		$destination = rtrim($destination, '/') . '/';
		if (!$is_rec)
		{
			$pinfo = pathinfo($source);
			$destination .= $pinfo['basename'] . '/';
		}
		if (!$is_dir($destination))
		{
			$mkdir($destination, 0755, true);
		}

		$files = $scandir($source);
		foreach ($files as $file)
		{
			if ($file != "." && $file != "..")
			{
				rrename($source . $file, $destination . $file, true, $function_prefix);
			}
		}
		$deleteDir( $source );
	}
	else
	{
		if ($file_exists($source))
		{
			if ($is_dir(rtrim($destination, '/') . '/'))
			{
				$pinfo = pathinfo($source);
				$dest2 = rtrim($destination, '/') . '/' . $pinfo['basename'];
			}
			else
			{
				$dest2 = $destination;
			}

			$rename($source, $dest2);
		}
	}
}

// On windows rename leaves folders sometime
// This will clear leftover folders
// After more feedback will merge it with rrename
function rrename_after_cleaner($source)
{
	$files = scandir($source);

	foreach ($files as $file)
	{
		if ($file != "." && $file != "..")
		{
			if (is_dir($source . DIRECTORY_SEPARATOR . $file))
			{
				rrename_after_cleaner($source . DIRECTORY_SEPARATOR . $file);
			}
			else
			{
				unlink($source . DIRECTORY_SEPARATOR . $file);
			}
		}
	}

	return rmdir($source);
}

/**
 * Recursive chmod
 * @param  string  $source
 * @param  int     $mode
 * @param  string  $rec_option
 * @param  bool    $is_rec
 */
function rchmod($source, $mode, $rec_option = "none", $is_rec = false)
{
	if ($rec_option == "none")
	{
		chmod($source, $mode);
	}
	else
	{
		if ($is_rec === false)
		{
			chmod($source, $mode);
		}

		$files = scandir($source);

		foreach ($files as $file)
		{
			if ($file != "." && $file != "..")
			{
				if (is_dir($source . DIRECTORY_SEPARATOR . $file))
				{
					if ($rec_option == "folders" || $rec_option == "both")
					{
						chmod($source . DIRECTORY_SEPARATOR . $file, $mode);
					}
					rchmod($source . DIRECTORY_SEPARATOR . $file, $mode, $rec_option, true);
				}
				else
				{
					if ($rec_option == "files" || $rec_option == "both")
					{
						chmod($source . DIRECTORY_SEPARATOR . $file, $mode);
					}
				}
			}
		}
	}
}

/**
 * Check if chmod is valid
 *
 * @param  $perm
 * @param  $val
 *
 * @return  bool
 */
function chmod_logic_helper($perm, $val)
{
	$valid = array(
		1 => array( 1, 3, 5, 7 ),
		2 => array( 2, 3, 6, 7 ),
		4 => array( 4, 5, 6, 7 )
	);

	if (in_array($perm, $valid[ $val ]))
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * @param  string  $input
 * @param  bool    $trace
 * @param  bool    $halt
 */
function debugger($input, $trace = false, $halt = false)
{
	ob_start();

	echo "<br>----- DEBUG DUMP -----";
	echo "<pre>";
	var_dump($input);
	echo "</pre>";

	if ($trace)
	{
		if (is_php('5.3.6'))
		{
			$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		else
		{
			$debug = debug_backtrace(false);
		}

		echo "<br>-----STACK TRACE-----";
		echo "<pre>";
		var_dump($debug);
		echo "</pre>";
	}

	echo "</pre>";
	echo "---------------------------<br>";

	$ret = ob_get_contents();
	ob_end_clean();

	echo $ret;

	if ($halt == true)
	{
		exit();
	}
}

/**
 * @param  string  $version
 *
 * @return  bool
 */
function is_php($version = '5.0.0')
{
	static $phpVer;
	$version = (string) $version;

	if ( ! isset($phpVer[ $version ]))
	{
		$phpVer[ $version ] = (version_compare(PHP_VERSION, $version) < 0) ? false : true;
	}

	return $phpVer[ $version ];
}
