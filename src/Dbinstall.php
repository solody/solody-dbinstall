<?php
/**
 * The class for maintain database structure.
 */
namespace Solody\Dbinstall;

use Zend\Db\Metadata\Metadata;
use Zend\Db\Adapter\Adapter;
use Zend\Config\Config;
use Zend\Config\Writer\PhpArray as ConfigWriter;
use Zend\Db\Sql\Ddl\CreateTable;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Sql;

class Dbinstall
{
    public $config = NULL;
    public $installed = false;
    
    private $createTables = NULL;
    private $inserts = NULL;
    
    protected $adapter;
    protected $metadata;
    
    private $sql;

    function __construct($config)
    {
        $this->config = $config;
        $this->adapter = new Adapter(array(
            'driver' => $config['driver'],
            'hostname'=>$config['hostname'],
            'username' => $config['username'],
            'password' => $config['password'],
        ));
        
        $this->metadata = new Metadata($this->adapter);
        $dbs = $this->metadata->getSchemas();
        
        if (in_array($config['database'], $dbs)){
            $this->installed = true;
        }
    }
    
    /**
     * Where start to install database structure.
     */
    public function install(){
        
        if (!$this->installed){
            $this->create_database();
            $this->create_tables();
            $this->insert_rows();
        }

    }
    
    private function create_database() {

        // Try to create a database named by $post_data->database
        try {
        
            $createDatabse_stament = $this->adapter->createStatement('CREATE DATABASE IF NOT EXISTS `'.$this->config['database'].'` CHARACTER SET = utf8 COLLATE = utf8_general_ci');
            $rs = $createDatabse_stament->execute();
            
            $this->adapter = new Adapter($this->config);
            $this->metadata = new Metadata($this->adapter);
            $this->sql = new Sql($this->adapter);
            
            if ( $this->adapter->getCurrentSchema() == $this->config['database'] ){
                
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
        
        $config_path = 'config/autoload';
        $config_file = $config_path.'/local.php';
        
        // If there is a config file at that path, so merge the configuration to it.
        $config = array();
        if (file_exists($config_file)) $config = include 'config/autoload/local.php';
            
        $reader = new Config($config);
        $reader->merge($local_db_config);
        
        $writer = new ConfigWriter();
        @mkdir($config_path,0777,true);
        $writer->toFile($config_file, $reader);
        
    }
    
    public function addCreateTable(CreateTable $table) {
        if (empty($this->createTables)) $this->createTables = array();
        array_push($this->createTables, $table);
        return $this;
    }
    
    public function addInsert(Insert $insert) {
        if (empty($this->inserts)) $this->inserts = array();
        array_push($this->inserts, $insert);
        return $this;
    }
    
    /**
     * Compose the tables structure data, and create them by $this->create_table();
     */
    private function create_tables(){
        $this->exec_sqlobjs($this->createTables);
    }
    
    /**
     * Insert the base data rows.
     */
    private function insert_rows(){
        $this->exec_sqlobjs($this->inserts);
    }
    
    private function exec_sqlobjs($objects){
        if (!empty($objects)) {
            foreach ($objects as $object) {
                $this->adapter->query(
                    $this->sql->getSqlStringForSqlObject($object),
                    Adapter::QUERY_MODE_EXECUTE
                    );
            }
        }
    }
    

}

?>