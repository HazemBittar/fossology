#!/usr/bin/php
<?php
/*
 Copyright (C) 2011 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * xml2html
 * \brief transform an xml output into html using the supplied style sheet
 *
 * @version "$Id$"
 *
 * Created on Oct 14, 2010
 */

/*
 * xml2html
 *
 * xml2html [- h] -f <file> [-o <file>] -x <file>
 *
 * -h usage
 * -f file the xml input file
 * -o optional output file, will overwrite if it exists
 * -x xsl file to use in the transformation
 *
 */

$usage = "{$argv[0]} [-h] -f <file> [-o <file>] -x <file>\n" .
         "Options:\n" .
         "-h: usage\n" .
         "-f <file>: the xml input file\n" .
         "-o <file>: optional output filepath, overwritten if exists. StdOut Default\n" .
         "-x <file>: the xsl style sheet file to use in the transformation\n";

// process options
$options = getopt('hf:o:x:');

if(array_key_exists('h',$options))
{
  echo "$usage\n";
  exit(0);
}

if(array_key_exists('f',$options))
{
  // make sure it exists and readable
  if(is_readable($options['f']))
  {
    $xmlFile = $options['f'];
  }
  else
  {
    echo "FATAL: xml file {$options['f']} does not exist or cannot be read\n";
    exit(1);
  }
}

if(array_key_exists('o',$options))
{
  $outputFile = $options['o'];
}

if(array_key_exists('x',$options))
{
  // make sure it exists and readable
  if(is_readable($options['x']))
  {
    $xslFile = $options['x'];
  }
  else
  {
    echo "FATAL: xsl file {$options['x']} does not exist or cannot be read\n";
    exit(1);
  }
}

/* Debug
echo "XML2J: after parameter processing\n";
echo "infile:$xmlFile,\nout:$outputFile,\nxsl:$xslFile\n";
echo "XMLJ2: we are operating at:" . getcwd() . "\n";
*/

$xsl = new XSLTProcessor();
$xsldoc = new DOMDocument();
$xsldoc->load($xslFile);
$xsl->importStyleSheet($xsldoc);

$xmldoc = new DOMDocument();
$xmldoc->load($xmlFile);
$transformed = $xsl->transformToXML($xmldoc);

if($outputFile)
{
  // open file and write output
  $OF = fopen($outputFile, 'w') or
    die("Fatal cannot open output file $outputFile\n");
  $wrote = fwrite($OF, $transformed);
  fclose($OF);
}
else
{
  // no output file, echo to stdout
  echo $transformed;
}
?>
