<?php

define('__REAL_AUTOINDEX_PATH', __DIR__.DIRECTORY_SEPARATOR);
define('__PHP52__', version_compare((float)phpversion(), (float)'5.2.17', '<='));
define('__PHP53__', version_compare((float)phpversion(), (float)'5.3', '<='));

function p($obj)
{
	echo '<pre>';
	print_r($obj);
	echo '</pre>';
}

function copyFile($source, $dest)
{
	$is_dot = array ('.', '..');
	if (is_dir($source))
	{
		if (__PHP53__)
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($source),
				RecursiveIteratorIterator::SELF_FIRST
			);
		}
		else
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);
		}

		foreach ($iterator as $file)
		{
			if (__PHP52__)
			{
				if (in_array($file->getBasename(), $is_dot))
					continue;
			}
			elseif (__PHP53__)
			{
				if ($file->isDot())
					continue;
			}

			if ($file->isDir())
				mkdir($dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName(), true);
			else
				copy($file, $dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName());
		}
		unset($iterator, $file);
	}
	else
		copy($source, $dest);

	return true;
}

function addIndex($path, $cli = false)
{
	$is_dot = array ('.', '..');
	$file_extension = substr(strrchr($path, '.'), 1);
	if (is_dir($path))
	{
		if (__PHP53__)
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path),
				RecursiveIteratorIterator::SELF_FIRST
			);
		}
		else
		{
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);
		}

		foreach ($iterator as $pathname => $file)
		{
			if (__PHP52__)
			{
				if (in_array($file->getBasename(), $is_dot))
					continue;
			}
			elseif (__PHP53__)
			{
				if ($file->isDot())
					continue;
			}

			$name = (string)trim($file->getFilename());
			$exp = explode('\\', $pathname);
			$dirname = isset($exp[0])? $exp[0].'/' : '';
			if(count($exp) === 2 && $file->isFile())
			{
				if (!file_exists($dirname.'index.php'))
				{
					if (copyFile(__REAL_AUTOINDEX_PATH.'sources/index.php', $dirname.'index.php') === true)
						continue;
				}
			}
			else
			{
				if ($file->isDir())
				{
					$dirname = str_replace('\\', '/', $file->getPathname().'/');
					if (!file_exists($dirname.'index.php'))
					{
						if (copyFile(__REAL_AUTOINDEX_PATH.'sources/index.php', $dirname.'index.php') === true)
							continue;
					}
				}
			}
		}
		unset($iterator, $pathname, $file);

		$msg = 'index.php added in '.$path;
		if ($cli === true)
			echo $msg."\n";
		else
			p($msg);
	}
	elseif ($file_extension === 'zip')
	{
		if (class_exists('ZipArchive'))
		{
			$add_index = array();
			$zip = new ZipArchive();
			$res = $zip->open($path);
			if ($res === true)
			{
				for ($i = 0; $i < $zip->numFiles; $i++)
				{
					$stat = $zip->statIndex($i);
					if (!empty($stat))
					{
						$file_info = pathinfo($stat['name']);
						if (!empty($file_info))
						{
							$dirname = trim($file_info['dirname']);
							$filename = trim($file_info['filename']);
							$basename = trim($file_info['basename']);
							if (!in_array($dirname, $is_dot))
							{
								$getFromName = $zip->getFromName($dirname.'/index.php');
								if (empty($getFromName))
								{
									$add_index[] = $dirname.'/';
								}
							}
						}
					}
				}

				$add_index = array_unique($add_index);
				foreach ($add_index as $dir_path)
				{
					if ($zip->addFile(__REAL_AUTOINDEX_PATH.'sources/index.php', $dir_path.'index.php') === true)
						continue;
				}
				unset($add_index,  $dir_path);

				$zip->close();
				unset($zip);

				$msg = 'index.php added in '.$path;
				if ($cli === true)
					echo $msg."\n";
				else
					p($msg);
			}
		}
		else
		{
			if ($cli === true)
				echo "You need to install ZipArchive\npecl install zip\n";
			else
				p('You need to install ZipArchive<br />pecl install zip');
		}
	}
	else
	{
		$msg = $path.' isn\'t a directory or zip file';
		if ($cli === true)
			echo $msg."\n";
		else
			p($msg);
	}
}

if (php_sapi_name() === 'cli')
{
	if (isset($argv) &&  (isset($argc) && $argc >= 2))
	{
		array_shift($argv);
		foreach($argv as $dir)
			addIndex($dir, true);
	}
}
elseif (isset($argv) &&  (isset($argc) && $argc < 2))
{
	echo 'Usage: php [directory...]';
	echo "\n\t".'php index.php /var/www/prestashop1611/modules/mymodule/'."\n";
}
else
{
	if(isset($_GET['path']))
	{
		$get_paths = $_GET['path'];
		$paths = explode(',', $get_paths);
		foreach($paths as $path)
			addIndex(trim(strip_tags($path)));
	}
	else
	{
		if(!empty($_POST))
		{
			$get_paths = $_POST['path'];
			$paths = explode(',', $get_paths);
			foreach($paths as $path)
				addIndex(trim(strip_tags($path)));
		}
		else
		{
			die();
		}
	}
}
