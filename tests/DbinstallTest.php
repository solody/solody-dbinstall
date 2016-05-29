<?php
use Solody\Dbinstall\Dbinstall;
use Zend\Db\Sql\Ddl\CreateTable;
use Zend\Db\Sql\Ddl\Column;
use Zend\Db\Sql\Ddl\Constraint;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\Metadata;
use Zend\Config\Config;

class DbinstallTest extends PHPUnit_Framework_TestCase
{    
    public function testInstall(){
        
        $config = array(
            'driver' => 'Pdo_mysql',
            'hostname' => 'localhost',
            'username' => 'root',
            'password' => 'abc123',
            'database' => 'dbinstall_test',
        );
        
        $dbinstall = new Dbinstall($config);
        
        $table_test = new CreateTable('test');
        $table_test->addColumn(new Column\Integer('id',FALSE,NULL,array('autoincrement'=>true)))
                   ->addConstraint(new Constraint\PrimaryKey('id'))
                   ->addColumn(new Column\Varchar('name',50));
        $dbinstall->addCreateTable($table_test);
        
        $insert_test = new Insert('test');
        $insert_test->values(array('name'=>'kent'));
        $dbinstall->addInsert($insert_test);
        
        $dbinstall->install();
        
        $adapter = new Adapter($config);
        $md = new Metadata($adapter);
        $sql = new Sql($adapter);
        
        $this->assertEquals($config['database'], $adapter->getCurrentSchema());
        $tables = $md->getTables();
        $this->assertEquals(1, count($tables));
        $this->assertEquals('test', $tables[0]->getName());
        
        $select = new Select('test');
        $select->where(array('name'=>'kent'));
        $statement = $sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();
        $this->assertEquals(1, $results->count());
        $row = $results->current();
        $this->assertEquals('kent', $row['name']);
        
        $config_file = './config/autoload/local.php';
        $this->assertEquals(true, file_exists($config_file));
        if (file_exists($config_file)) $test_config = include $config_file;
        $test_config_reader = new Config($test_config);
        $this->assertEquals($config, $test_config_reader->get('db')->toArray());
        
        $adapter->createStatement('DROP DATABASE IF EXISTS `'.$config['database'].'`')
                ->execute();
        unlink('./config/autoload/local.php');
        rmdir('./config/autoload');
        rmdir('./config');
        
    }
}

?>