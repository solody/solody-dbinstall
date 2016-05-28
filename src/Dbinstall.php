<?php
/**
 * The class for maintain database structure.
 */
namespace Solody\Dbinstall;

use Zend\Db\Metadata\Metadata;
use Zend\Db\Adapter\Adapter;
use Zend\Config\Config;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Ddl;
use Zend\Db\Sql\Ddl\Column;
use Zend\Db\Sql\Ddl\Constraint;


abstract class Dbinstall extends Metadata
{
    public $config = NULL;
    
    private $tables = NULL;

    function __construct($config)
    {
        $this->config = $config;
        parent::__construct(new Adapter(array(
            'driver' => $config['driver'],
            'hostname'=>$config['hostname'],
            'username' => $config['username'],
            'password' => $config['password'],
        )));
        
    }
    
    /**
     * Where start to install database structure.
     */
    public function install(){
        
        $this->create_database();
        
        $this->create_tables();
        
        $this->insert_rows();
        
    }
    
    private function create_database() {

        // Try to create a database named by $post_data->database
        try {
        
            $createDatabse_stament = $this->adapter->createStatement('CREATE DATABASE IF NOT EXISTS `'.$this->config['database'].'` CHARACTER SET = utf8 COLLATE = utf8_general_ci');
            $rs = $createDatabse_stament->execute();
            
            parent::__construct(new Adapter($this->config));
            
            if ( $this->adapter->getCurrentSchema() == $this->config['database'] ){
                
                $this->tables = $this->getTableNames();
                
                $local_db_config = new Config(array('db'=>$this->config), true);
                $this->generateConfigFile($local_db_config);
                
            }
        
        } catch (\Exception $e) {
            throw $e;
        }
        
        
    }
    /**
     * Generate the config file config/autoload/local.php for database session
     * @param Config $local_db_config
     */
    private function generateConfigFile(Config $local_db_config){
        
        $config_file = 'config/autoload/local.php';
        
        // If there is a config file at that path, so merge the configuration to it.
        $config = array();
        if (file_exists($config_file)) $config = include 'config/autoload/local.php';
            
        $reader = new Config($config);
        $reader->merge($local_db_config);
        
        $writer = new \Zend\Config\Writer\PhpArray();
        $writer->toFile($config_file, $reader);
        
    }

    /**
     * Create one table. if it is exsist, check the columns. if the column is exsist, change it's type, else create the column.
     * @param unknown $tableName
     * @param array $table
     */
    private function create_table( $tableName, $tableStructureData) {
    
        $adapter = $this->adapter;
        $sql = new Sql($adapter);
    
        if (!in_array($tableName, $this->tables)){
    
            // Create the $table
            $CreateTable = new Ddl\CreateTable($tableName);
            
            if (!empty($tableStructureData['column'])){
                foreach ($tableStructureData['column'] as $column){
                    $CreateTable->addColumn($column);
                }
            }
            
            if (!empty($tableStructureData['constraint'])){
                foreach ($tableStructureData['constraint'] as $constraint){
                    $CreateTable->addConstraint($constraint);
                }
            }
            
            
            $adapter->query(
                $sql->getSqlStringForSqlObject($CreateTable),
                $adapter::QUERY_MODE_EXECUTE
            );
    
        }else{
    
            // Check the columns
            $columns = $this->getColumns($tableName);
            $constraints = $this->getConstraints($tableName); 
            $AlterTable = new Ddl\AlterTable($tableName);
            
            if (!empty($tableStructureData['column'])){
                foreach ($tableStructureData['column'] as $createColumn){
                
                    $column_exsist = false;
                
                    foreach ($columns as $column){
                        if ($createColumn->getName() == $column->getName()) $column_exsist = true;
                    }
                
                
                    if ($column_exsist) {
                
                        // Alter the table, change the column.
                        $AlterTable->changeColumn($createColumn->getName(), $createColumn);
                
                    }else{
                
                        // Alter the table, add the column.
                        $AlterTable->addColumn($createColumn);
                
                    }
                
                }
            }
            
            
            
            // Delete exsisted constraints(mysql index) but PRIMARY KEY
            $exsisted_constraints = $this->getConstraints($tableName);
            
            foreach ($exsisted_constraints as $exsisted_constraint){
                if ($exsisted_constraint->getType() != 'PRIMARY KEY'){
            
                    $adapter->query(
                        'ALTER TABLE `'.$tableName.'`
                                 DROP index `'.str_replace('_zf_'.$tableName.'_', '', $exsisted_constraint->getName()).'`',
                        $adapter::QUERY_MODE_EXECUTE
                    );
            
                }
            }
            
            // Add all constraints but PRIMARY KEY
            if (!empty($tableStructureData['constraint'])){
                foreach ($tableStructureData['constraint'] as $constraint){
                
                    if ($constraint instanceof Constraint\PrimaryKey){
                        // Do nothing
                    }else{
                        // Add to DB
                        $AlterTable->addConstraint($constraint);
                    }
                
                }
            }
            
            
            $adapter->query(
                $sql->getSqlStringForSqlObject($AlterTable),
                $adapter::QUERY_MODE_EXECUTE
            );
            
            
        }
    }
    
    /**
     * Create Recent columns quickly.
     * @param unknown $type
     * @param unknown $name
     * @return \Zend\Db\Sql\Ddl\Column\Integer|\Zend\Db\Sql\Ddl\Column\Varchar|\Zend\Db\Sql\Ddl\Column\Text|\Zend\Db\Sql\Ddl\Column\Datetime
     */
    private function createColumn($type,$name = null)
    {
        $column = null;
        if (empty($name)){
            if ($type=='acid') $name = 'id';
            $name = $type;
        }
        
        switch ($type) {
            
            case 'acid':
                $column = new Column\Integer($name,FALSE,NULL,array('autoincrement'=>true));
            break;
            
            case 'name':
                $column = new Column\Varchar($name, 50);
            break;
            
            case 'type':
                $column = new Column\Varchar($name, 50);
            break;
            
            case 'text':
                $column = new Column\Text($name);
            break;
            
            case 'time':
                $column = new Column\Datetime($name);
            break;
            
            case 'image':
                $column = new Column\Varchar($name, 255);
            break;
            
            case 'page_title':
                $column = new Column\Varchar($name, 255);
            break;
            
            case 'page_keywords':
                $column = new Column\Varchar($name, 500);
            break;
            
            case 'page_description':
                $column = new Column\Varchar($name, 1000);
            break;
            
            case 'rlid':
                $column = new Column\Integer($name,TRUE,NULL);
            break;
            
            default:
                throw new \Exception('unknow column type.');
            break;
        }
        
        
        return $column;
    }
    
    /**
     * Create Recent constraint quickly.
     * @param unknown $type
     * @param unknown $columnName
     * @throws \Exception
     */
    private function createConstraint($type,$columnName)
    {
        switch ($type) {
        
            case 'pk':
                $column = new Constraint\PrimaryKey($columnName);
                break;
        
            default:
                throw new \Exception('unknow constraint type.');
                break;
        }
    }
    
    /**
     * Compose the tables structure data, and create them by $this->create_table();
     */
    abstract protected function create_tables();
    
    /**
     * Insert the base data rows.
     */
    abstract protected function insert_rows();
    

}

?>