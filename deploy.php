#!/usr/bin/php
<?php
if (!isset($argv[1])) {
  usage('See README');
} else if (function_exists($argv[1])) {
  array_shift($argv);
  call_user_func_array(array_shift($argv), $argv);
} else {
  deploy(isset($argv[1]) ? $argv[1] : 'master');
}

function deploy($branch, $parameter = false) {
  //Validate configuration
  if (is_file('./.git/ftpconfig')) {
    $ini_file = parse_ini_file('./.git/ftpconfig', true);
  } else {
    error('No FTP configfile found.');
  }
  if (!isset($ini_file[$branch]) || !is_array($ini_file[$branch])) {
    error("No FTP configuration for branch '" . $branch . "'.");
  } else {
    $config = $ini_file[$branch];
  }

  //Login to ftp-server
  $stream = @ftp_connect($config['server'], isset($config['port']) ? $config['port'] : 21) or error("Could not connect to '". $config['server'] . "'");
  if (!@ftp_login($stream, $config['username'], $config['password'])) {error("Could not log in with username '" . $config['username'] . "'");}
  
  //Get remote REVISION
  $temp = fopen('php://temp', 'r+'); 
  if (@ftp_fget($stream, $temp, $config['remote_path'] . 'REVISION', FTP_ASCII, 0)) { 
    rewind($temp); 
    $remote_revision = stream_get_contents($temp);
  } else {
    $remote_revision = false;
  }

  //Get local REVISION
  $local_revision = exec('git rev-parse HEAD');

  //Compare REVISIONs/Get filelist
  if ($remote_revision==$local_revision) {
    error('Uploaded revision is already newest revision (' . $local_revision . ')');
  } else if ($remote_revision) {
    exec('git diff --name-status ' . $remote_revision . '..' . $local_revision, $gitlist);
    $filelist = array();
    foreach ($gitlist as $gitfile) {
      $gitfile_array = explode("\t", $gitfile);
      $filelist[] = array($gitfile_array[1], $gitfile_array[0]);
    }
  } else {
    exec('git ls-tree --name-only -r HEAD', $gitlist);
    foreach ($gitlist as $gitfile) {
      $filelist[] = array($gitfile, 'A');
    }
  }

  //Update remote FTP files
  foreach ($filelist as $file) {
    $filename = $file[0];
    $filemode = $file[1];
    switch ($filemode) {
      case 'A': //Upload new files
      case 'M': //Upload modified files
        if (!@ftp_put($stream, $config['remote_path'] . $filename, $filename, FTP_BINARY)) {
          $tree_dir = '';
          $tree = explode('/', $filename);
          array_pop($tree);
          foreach ($tree as $child) {
            $tree_dir .= '/' . $child;
            if (!@ftp_chdir($stream, $config['remote_path'] . trim($tree_dir, '/'))) {
              if (!ftp_mkdir($stream, $config['remote_path'] . trim($tree_dir, '/'))) {
                error("Failed creating remote directory '" . $tree_dir . "'");
              }
              ftp_chdir($stream, $config['remote_path'] . trim($tree_dir, '/'));
            }
          }
          if (is_dir($filename)) {
            ftp_mkdir($stream, $config['remote_path'] . $filename);
          } else if (is_file($filename)) {
            if (ftp_put($stream, $config['remote_path'] . $filename, $filename, FTP_BINARY)) {
              done("Uploaded '" . $filename ."'");
            } else {
              error("Can't upload file '" . $config['remote_path'] . $filename . "'");
            }
          } else {
            error("Can't upload file '" . $config['remote_path'] . $filename . "'");
          }
        } else {
          done("Uploaded '" . $filename ."'");
        }
        break;
      case 'D': //Delete removed files
        if (is_dir($filename)) {
          ftp_rmdir($stream, $config['remote_path'] . $filename);          
        } else {
          ftp_delete($stream, $config['remote_path'] . $filename);          
        }
        done("Deleted '" . $filename ."'");    
        break;
    }    
  }

  #Upload new REVISION file
  $temp = fopen('php://temp', 'r+');
  fwrite($temp, $local_revision);
  rewind($temp);
  ftp_fput($stream, $config['remote_path'] . 'REVISION', $temp, FTP_ASCII);

  ftp_close($stream);
}

//Config
function config($function = false) {
  switch ($function) {
    case false:
      usage('Available functions; show,edit');
      break;
    case 'show':
      echo file_get_contents('./.git/ftpconfig');
      break;
    case 'edit':
      system('subl ./.git/ftpconfig');
      break;
  }
}

//Help
function help($function = false) {
  switch ($function) {
    case false:
      usage('todo');
      break;
    case 'show':
      usage('todo');
      break;
    case 'edit':
      usage('todo');
      break;
  }
}

//Error function
function error($text) {
  echo "Deploy error: " . $text . "\n";exit;
}

//Done/Success function
function done($text) {
  echo $text . "\n";
}

//Usage function
function usage($text) {
  echo "Usage: " . $text . "\n";
}

echo "\n";
