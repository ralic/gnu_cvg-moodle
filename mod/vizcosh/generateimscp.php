<?PHP // $Id: generateimscp.php,v 1.2 2007/05/20 06:00:26 skodak Exp $
//Todo (subchapter und co)
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 2001-3001 Antonio Vicent          http://ludens.es      //
//           (C) 2001-3001 Eloy Lafuente (stronk7) http://contiento.com  //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->dirroot . '/backup/lib.php');
require_once($CFG->dirroot . '/lib/filelib.php');

$id = required_param('id', PARAM_INT);           // Course Module ID

require_login();

if (!$cm = get_coursemodule_from_id('vizcosh', $id)) {
    error('Course Module ID was incorrect');
}

if (!$course = get_record('course', 'id', $cm->course)) {
    error('Course is misconfigured');
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('moodle/course:manageactivities', $context);
 
if (!$vizcosh = get_record('vizcosh', 'id', $cm->instance)) {
    error('Course module is incorrect');
}

$strvizcoshs = get_string('modulenameplural', 'vizcosh');
$strvizcosh  = get_string('modulename', 'vizcosh');
$strtop  = get_string('top', 'vizcosh');

add_to_log($course->id, 'vizcosh', 'generateimscp', 'generateimscp.php?id='.$cm->id, $vizcosh->id, $cm->id);

/// Get all the chapters
    $chapters = get_records('vizcosh_chapters', 'vizcoshid', $vizcosh->id, 'pagenum');

/// Generate the manifest and all the contents
    chapters2imsmanifest($chapters, $vizcosh, $cm);

/// Now zip everything
    make_upload_directory('temp');
    $zipfile = $CFG->dataroot . "/temp/". time() . '.zip';
    $files = get_directory_list($CFG->dataroot . "/$cm->course/moddata/vizcosh/$vizcosh->id", basename($zipfile), false, true, true);
    foreach ($files as $key => $value) {
        $files[$key] = $CFG->dataroot . "/$cm->course/moddata/vizcosh/$vizcosh->id/" . $value;
    }
    zip_files($files, $zipfile);
/// Now delete all the temp dirs
    delete_dir_contents($CFG->dataroot . "/$cm->course/moddata/vizcosh/$vizcosh->id");
/// Now serve the file
    send_file($zipfile, clean_filename($vizcosh->name) . '.zip', 86400, 0, false, true);

/**
 * This function will create the default imsmanifest plus contents for the vizcosh chapters passed as array
 * Everything will be created under the vizcosh moddata file area *
 */
function chapters2imsmanifest ($chapters, $vizcosh, $cm) {

    global $CFG;

/// Init imsmanifest and others
    $imsmanifest = '';
    $imsitems = '';
    $imsresources = '';

/// Moodle and VizCoSH version
    $moodle_release = $CFG->release;
    $moodle_version = $CFG->version;
    $vizcosh_version   = get_field('modules', 'version', 'name', 'vizcosh');

/// Load manifest header
    $imsmanifest .= '<?xml version="1.0" encoding="UTF-8"?>
<!-- This package has been created with Moodle ' . $moodle_release . ' (' . $moodle_version . '), VizCoSH module version ' . $vizcosh_version . ' - http://moodle.org -->
<!-- One idea and implementation by Eloy Lafuente (stronk7) and Antonio Vicent (C) 2001-3001 -->
<manifest xmlns="http://www.imsglobal.org/xsd/imscp_v1p1" xmlns:imsmd="http://www.imsglobal.org/xsd/imsmd_v1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" identifier="MANIFEST-' . md5($CFG->wwwroot . '-' . $vizcosh->course . '-' . $vizcosh->id) . '" xsi:schemaLocation="http://www.imsglobal.org/xsd/imscp_v1p1 imscp_v1p1.xsd http://www.imsglobal.org/xsd/imsmd_v1p2 imsmd_v1p2p2.xsd">
  <organizations default="MOODLE-' . $vizcosh->course . '-' . $vizcosh->id . '">
    <organization identifier="MOODLE-' . $vizcosh->course . '-' . $vizcosh->id . '" structure="hierarchical">
      <title>' . htmlspecialchars($vizcosh->name) . '</title>';

/// Create the temp directory
    $moddir = "$cm->course/moddata/vizcosh/$vizcosh->id";
    make_upload_directory($moddir);

/// For each chapter, create the corresponding directory and save contents there

/// To store the prev level (vizcosh only have 0 and 1)
    $prevlevel = null;
    foreach ($chapters as $chapter) {
    /// Calculate current level ((vizcosh only have 0 and 1)
        $currlevel = empty($chapter->subchapter) ? 0 : 1;
    /// Based upon prevlevel and current one, decide what to close
        if ($prevlevel !== null) {
        /// Calculate the number of spaces (for visual xml-text formating)
            $prevspaces = substr('                ', 0, $currlevel * 2);

        /// Same level, simply close the item
            if ($prevlevel == $currlevel) {
                $imsitems .= $prevspaces . '        </item>' . "\n";
            }
        /// Bigger currlevel, nothing to close
        /// Smaller currlevel, close both the current item and the parent one
            if ($prevlevel > $currlevel) {
                $imsitems .= '          </item>' . "\n";
                $imsitems .= '        </item>' . "\n";
            }
        }
    /// Update prevlevel
        $prevlevel = $currlevel;

    /// Calculate the number of spaces (for visual xml-text formating)
        $currspaces = substr('                ', 0, $currlevel * 2);

    /// Create the static html file + local attachments (images...)
        $chapterdir = "$moddir/$chapter->pagenum";
        make_upload_directory($chapterdir);
        $chaptercontent = chapter2html($chapter, $vizcosh->course, $vizcosh->id);
        file_put_contents($CFG->dataroot . "/" . $chapterdir . "/index.html", $chaptercontent->content);
    /// Add the imsitems
        $imsitems .= $currspaces .'        <item identifier="ITEM-' . $vizcosh->course . '-' . $vizcosh->id . '-' . $chapter->pagenum .'" isvisible="true" identifierref="RES-' . $vizcosh->course . '-' . $vizcosh->id . '-' . $chapter->pagenum . '">
 ' . $currspaces . '         <title>' . htmlspecialchars($chapter->title) . '</title>' . "\n";

    /// Add the imsresources
    /// First, check if we have localfiles
        $localfiles = '';
        if ($chaptercontent->localfiles) {
            foreach ($chaptercontent->localfiles as $localfile) {
                $localfiles .= "\n" . '      <file href="' . $chapter->pagenum . '/' . $localfile . '" />';
            }
        }
    /// Now add the dependency to css
        $cssdependency = "\n" . '      <dependency identifierref="RES-' . $vizcosh->course . '-'  . $vizcosh->id . '-css" />';
    /// Now build the resources section
        $imsresources .= '    <resource identifier="RES-' . $vizcosh->course . '-'  . $vizcosh->id . '-' . $chapter->pagenum . '" type="webcontent" xml:base="' . $chapter->pagenum . '/" href="index.html">
      <file href="' . $chapter->pagenum . '/index.html" />' . $localfiles . $cssdependency . '
    </resource>' . "\n";
    }

/// Close items (the latest chapter)
/// Level 1, close 1
    if ($currlevel == 0) {
        $imsitems .= '        </item>' . "\n";
    }
/// Level 2, close 2
    if ($currlevel == 1) {
        $imsitems .= '          </item>' . "\n";
        $imsitems .= '        </item>' . "\n";
    }

/// Define the css common resource
$cssresource = '    <resource identifier="RES-' . $vizcosh->course . '-'  . $vizcosh->id . '-css" type="webcontent" xml:base="css/" href="styles.css">
      <file href="css/styles.css" />
    </resource>' . "\n";

/// Add imsitems to manifest
    $imsmanifest .= "\n" . $imsitems;
/// Close the organization
    $imsmanifest .= "    </organization>
  </organizations>";
/// Add resources to manifest
    $imsmanifest .= "\n  <resources>\n" . $imsresources . $cssresource . "  </resources>";
/// Close manifest
    $imsmanifest .= "\n</manifest>\n";

    file_put_contents($CFG->dataroot . "/" . $moddir . '/imsmanifest.xml', $imsmanifest );

/// Now send the css resource
    make_upload_directory("$moddir/css");
    file_put_contents($CFG->dataroot . "/" . $moddir . "/css/styles.css", file_get_contents("$CFG->dirroot/mod/vizcosh/generateimscp.css"));
}

/**
 * This function will create one chaptercontent object, with the contents converted to html and 
 * one array of local images to be included
 */
function chapter2html($chapter, $courseid, $vizcoshid) {

    global $CFG;

    $content = '';
    $content .= '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">' . "\n";
    $content .= '<html>' . "\n";
    $content .= '<head>' . "\n";
    $content .= '<meta http-equiv="content-type" content="text/html; charset=utf-8" />' . "\n";
    $content .= '<link rel="stylesheet" type="text/css" href="../css/styles.css" />' . "\n";
    $content .= '<title>' . $chapter->title . '</title>' . "\n";
    $content .= '</head>' . "\n";
    $content .= '<body>' . "\n";
    $content .= '<h1 id="header">' . $chapter->title . '</h1>' ."\n";
    $options = new object();
    $options->noclean = true;
    $content .= format_text($chapter->content, '', $options, $courseid) . "\n";
    $content .= '</body>' . "\n";
    $content .= '</html>' . "\n";

/// Now look for course-files in contents
    $search = array($CFG->wwwroot.'/file.php/'.$courseid,
                    $CFG->wwwroot.'/file.php?file=/'.$courseid);
    $replace = array('$@FILEPHP@$','$@FILEPHP@$');
    $content = str_replace($search, $replace, $content);

    $regexp = '/\$@FILEPHP@\$(.*?)"/is';
    $localfiles = array();
    $basefiles = array();
    preg_match_all($regexp, $content, $list);

    if ($list) {
    /// Build the array of local files
        foreach (array_unique($list[1]) as $key => $value) {
            $localfiles['<#'. $key . '#>'] = $value;
            $basefiles['<#'. $key . '#>'] = basename($value);
        /// Copy files to current chapter directory
            if (file_exists($CFG->dataroot . '/' . $courseid . '/' . $value)) {
                copy($CFG->dataroot . '/' . $courseid . '/' . $value, $CFG->dataroot . '/' . $courseid . '/moddata/vizcosh/' . $vizcoshid . '/' . $chapter->pagenum . '/' . basename ($value));
            }
        }
    /// Replace contents by keys
        $content = str_replace($localfiles, array_keys($localfiles), $content);
    /// Replace keys by basefiles
        $content = str_replace(array_keys($basefiles), $basefiles, $content);
    /// Delete $@FILEPHP@$
        $content = str_replace('$@FILEPHP@$', '', $content);
    }

/// Build the final object needed to have all the info in order to create the manifest
    $object = new object();
    $object->content = $content;
    $object->localfiles = $basefiles;

    return $object;
}

?>
