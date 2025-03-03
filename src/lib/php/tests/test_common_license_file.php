<?php
/*
 SPDX-FileCopyrightText: © 2011-2013 Hewlett-Packard Development Company, L.P.

 SPDX-License-Identifier: GPL-2.0-only
*/

/**
 * \file test_common_license_file.php
 * \brief unit tests for common-license-file.php
 */

use Fossology\Lib\Db\ModernDbManager;
use Fossology\Lib\Test\TestPgDb;

require_once(dirname(__FILE__) . '/../common-license-file.php');
require_once(dirname(__FILE__) . '/../common-db.php');
require_once(dirname(__FILE__) . '/../common-dir.php');
require_once(dirname(__FILE__) . '/../common-ui.php');

/**
 * \class test_common_license_file
 */
class test_common_license_file extends \PHPUnit\Framework\TestCase
{
  public $upload_pk = 0;
  public $uploadtree_pk_parent = 0;
  public $uploadtree_pk_child = 0;
  public $agent_pk = 0;
  public $uploadtree_tablename = 'uploadtree';

  /** @var TestPgDb */
  private $testDb;

  /** @var ModernDbManager */
  private $dbManager;

  private $logFileName;

  /**
   * \brief initialization
   */
  protected function setUp() : void
  {
    $this->testDb = new TestPgDb("fosslibtest");
    $tables = array('license_ref','license_file','pfile','agent','upload','uploadtree');
    $this->testDb->createPlainTables($tables);
    $sequences = array('license_ref_rf_pk_seq', 'license_file_fl_pk_seq',
      'pfile_pfile_pk_seq', 'agent_agent_pk_seq', 'upload_upload_pk_seq',
      'uploadtree_uploadtree_pk_seq');
    $this->testDb->createSequences($sequences);
    $this->testDb->createConstraints([
      'rf_pkpk', 'license_file_pkey', 'pfile_pkey', 'agent_pkey', 'upload_pkey_idx', 'ufile_rel_pkey'
    ]);
    $this->testDb->alterTables($tables);
    $this->testDb->createViews(['license_file_ref']);
    // $this->testDb->insertData($tables);

    global $upload_pk;
    global $pfile_pk_parent;
    global $pfile_pk_child;
    global $agent_pk;

    $logger = new Monolog\Logger('default');
    $this->logFileName = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/db.sqlite.log';
    $logger->pushHandler(new Monolog\Handler\StreamHandler($this->logFileName, Monolog\Logger::ERROR));
    $this->dbManager = $this->testDb->getDbManager();

    /** preparation, add uploadtree, upload, pfile, license_file record */
    $upload_filename = "license_file_test"; /* upload file name */

    $this->dbManager->prepare($stmt='pfile.insert',
              $sql = "INSERT INTO pfile (pfile_sha1,pfile_md5,pfile_size) VALUES ($1,$2,$3)");
    $this->dbManager->freeResult($this->dbManager->execute($stmt,array(
      'AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC', '2239AA7DAC291B6F8D0A56396B1B8530', '4560')));
    $this->dbManager->freeResult($this->dbManager->execute($stmt,array(
      'B1938B14B9A573D59ABCBD3BF0F9200CE6E79FB6', '55EFE7F9B9D106047718F1CE9173B869', '1892')));

    /** add nomos agent record **/
    $this->dbManager->queryOnce($sql="INSERT INTO agent (agent_name) VALUES('nomos')");

    /** add license_ref record */
    $this->dbManager->prepare($stmt='license_ref.insert',
            $sql="INSERT INTO license_ref"
            . " (rf_pk, rf_shortname, rf_text, marydone, rf_active, rf_text_updatable, rf_detector_type)"
            . " VALUES ($1,$2,$3,$4,$5,$6,$7)");
    $this->dbManager->freeResult($this->dbManager->execute($stmt,
            array(1, 'test_ref', 'test_ref', 'false', 'true', 'false', 1)));

    /** get pfile id */
    $this->dbManager->prepare($stmt='license_ref.select',
          $sql = "SELECT pfile_pk from pfile where pfile_sha1"
            . " IN ('AF1DF2C4B32E4115DB5F272D9EFD0E674CF2A0BC', 'B1938B14B9A573D59ABCBD3BF0F9200CE6E79FB6')");
    $result = $this->dbManager->execute($stmt);
    $row = $this->dbManager->fetchArray($result);
    $pfile_pk_parent = $row['pfile_pk'];
    $row = $this->dbManager->fetchArray($result);
    $pfile_pk_child= $row['pfile_pk'];
    $this->dbManager->freeResult($result);

    /** add a license_file record */
    $agent_nomos = $this->dbManager->getSingleRow("SELECT agent_pk from agent where agent_name = 'nomos'",array(),__METHOD__.'.agent.select');
    $agent_pk = $agent_nomos['agent_pk'];
    $this->dbManager->prepare($stmt='license_file.insert',
                       $sql = "INSERT INTO license_file(rf_fk, agent_fk, pfile_fk) VALUES ($1,$2,$3)");
    $this->dbManager->freeResult($this->dbManager->execute($stmt, array(1, $agent_pk, $pfile_pk_parent)));
    $this->dbManager->freeResult($this->dbManager->execute($stmt, array(2, $agent_pk, $pfile_pk_child)));

    $this->dbManager->queryOnce("INSERT INTO upload (upload_filename,upload_mode,upload_ts, pfile_fk, uploadtree_tablename)"
            . " VALUES ('$upload_filename',40,now(), '$pfile_pk_parent', '$this->uploadtree_tablename')");
    $row = $this->dbManager->getSingleRow("SELECT upload_pk from upload where upload_filename = '$upload_filename'",array(),__METHOD__.'.upload.select');
    $upload_pk= $row['upload_pk'];

    $this->dbManager->prepare($stmtIn=__METHOD__.'.uploadtree.insert',
            "INSERT INTO uploadtree (parent, upload_fk, pfile_fk, ufile_mode, lft, rgt, ufile_name) VALUES ($1,$2,$3,$4,$5,$6,$7)");
    $this->dbManager->freeResult(
       $this->dbManager->execute($stmtIn,array(NULL, $upload_pk, $pfile_pk_parent, 33188, 1, 2, 'license_test.file.parent')));

    $this->dbManager->prepare($stmtOut=__METHOD__.'uploadtree.select',
            "SELECT uploadtree_pk from uploadtree where pfile_fk=$1");
    $res = $this->dbManager->execute($stmtOut,array($pfile_pk_parent));
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
    $this->uploadtree_pk_parent = $row['uploadtree_pk'];

    /** add child uploadtree record */
    $this->dbManager->freeResult(
       $this->dbManager->execute($stmtIn,array($this->uploadtree_pk_parent, $upload_pk, $pfile_pk_child, 33188, 1, 2, 'license_test.file.child')));

    $res = $this->dbManager->execute($stmtOut,array($pfile_pk_child));
    $row = $this->dbManager->fetchArray($res);
    $this->dbManager->freeResult($res);
    $this->uploadtree_pk_child = $row['uploadtree_pk'];

    $this->uploadtree_tablename = GetUploadtreeTableName($upload_pk);
    print('.');
  }

  /**
   * \brief testing from GetFileLicenses
   * in this test case, this pfile have only one license
   */
  function testGetFileLicenses()
  {
    global $agent_pk;

    $license_array = GetFileLicenses($agent_pk, '' , $this->uploadtree_pk_parent, $this->uploadtree_tablename);
    /** the expected license value */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $row = $this->dbManager->getSingleRow($sql);
    $license_value_expected = $row['rf_shortname'];
    $count = count($license_array);

    $this->assertEquals($license_value_expected, $license_array[1]);
    $this->assertEquals(1, $count);
  }

  /**
   * \brief testing from GetFileLicenses
   * in this test case, this pfile have 2 same license
   */
  function testGetFileLicensesDul()
  {
    global $pfile_pk_parent;
    global $agent_pk;
    $sql = "INSERT INTO license_file(rf_fk, agent_fk, pfile_fk) VALUES(1, $agent_pk, $pfile_pk_parent);";
    $this->dbManager->getSingleRow($sql, [], __METHOD__ . ".insert");

    $license_array = GetFileLicenses($agent_pk, '' , $this->uploadtree_pk_parent, $this->uploadtree_tablename, "yes");
    /** the expected license value */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $row = $this->dbManager->getSingleRow($sql, [], __METHOD__ . ".get");
    $license_value_expected = $row['rf_shortname'];

    $count = count($license_array);
    $this->assertEquals(2, $count);
    $this->assertEquals($license_value_expected, $license_array[1]);
    $this->assertEquals($license_value_expected, $license_array[3]);
  }

  /**
   * \brief testing from GetFileLicenses_tring
   * in this test case, this pfile have only one license
   */
  function testGetFileLicenses_string()
  {
    global $agent_pk;

    $license_string = GetFileLicenses_string($agent_pk, '', $this->uploadtree_pk_parent, $this->uploadtree_tablename);
    /** the expected license value */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $row = $this->dbManager->getSingleRow($sql);
    $license_value_expected = $row['rf_shortname'];

    $this->assertEquals($license_value_expected, $license_string);
  }

  /**
   * \brief testing for GetFilesWithLicense
   */
  function testGetFilesWithLicense()
  {
    global $pfile_pk_parent;
    global $agent_pk;

    /** get a license short name */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $row = $this->dbManager->getSingleRow($sql);
    $rf_shortname = $row['rf_shortname'];

    $files_result = GetFilesWithLicense($agent_pk, $rf_shortname, $this->uploadtree_pk_parent, false, 0, "ALL", "", null, $this->uploadtree_tablename);
    $row = pg_fetch_assoc($files_result);
    $pfile_id_actual = $row['pfile_fk'];
    pg_free_result($files_result);
    $this->assertEquals($pfile_pk_parent, $pfile_id_actual);
  }

  /**
   * \brief testing for Level1WithLicense
   */
  function testLevel1WithLicense()
  {
    global $agent_pk;

    /** get a license short name */
    $sql = "SELECT rf_shortname from license_ref where rf_pk = 1;";
    $row = $this->dbManager->getSingleRow($sql);
    $rf_shortname = $row['rf_shortname'];

    $file_name = Level1WithLicense($agent_pk, $rf_shortname, $this->uploadtree_pk_parent, false, $this->uploadtree_tablename);
    $this->assertEquals("license_test.file.child", $file_name[$this->uploadtree_pk_child]);
  }


  /**
   * \brief clean the env
   */
  protected function tearDown() : void
  {
    if (!is_callable('pg_connect')) {
      return;
    }
    $this->testDb->fullDestruct();
    $this->testDb = null;
    $this->dbManager = null;
  }
}
