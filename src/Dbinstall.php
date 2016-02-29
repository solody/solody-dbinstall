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


class Dbinstall extends Metadata
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
            
        $reader = new \Zend\Config\Config($config);
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
     * Compose the tables structure data, and create them by $this->create_table();
     */
    private function create_tables() {
        
        // Common columns
        $COLUMNS['id']              = new Column\Integer('id',FALSE,NULL,array('autoincrement'=>true));
        
        $COLUMNS['name']            = new Column\Varchar('name', 50);
        $COLUMNS['type']            = new Column\Varchar('type', 50);
        
        $COLUMNS['title']            = new Column\Varchar('title', 255);
        $COLUMNS['keywords']         = new Column\Varchar('keywords', 500);
        $COLUMNS['description']      = new Column\Varchar('description', 1000);
        $COLUMNS['text']            = new Column\Text('text');
        $COLUMNS['source_id']     = new Column\Integer('source_id',TRUE,NULL);
        
        $COLUMNS['category_id']     = new Column\Integer('category_id',FALSE,NULL);
        $COLUMNS['create_time']     = new Column\Datetime('create_time');
        
        $COLUMNS['image']           = new Column\Varchar('image', 255);
        
        $COLUMNS['photo_id']     = new Column\Integer('photo_id',FALSE,NULL);
        $COLUMNS['album_id']     = new Column\Integer('album_id',FALSE,NULL);
        $COLUMNS['thumb_id']     = new Column\Integer('thumb_id',TRUE,NULL);

        
        // Common constraints
        $CONSTRAINTS['id_primarykey'] = new Constraint\PrimaryKey('id');
        
        
        /**
         * Create table [article].
         */
        $table_article['column'] = array(
            $COLUMNS['id'],
            $COLUMNS['title'],
            $COLUMNS['keywords'],
            $COLUMNS['description'],
            $COLUMNS['thumb_id'],
            $COLUMNS['text'],
            $COLUMNS['source_id'],
            $COLUMNS['category_id'],
            $COLUMNS['create_time'],
        );
        $table_article['constraint'] = array(
            $CONSTRAINTS['id_primarykey']
        );
        $this->create_table( 'article', $table_article);

        
        /**
         * Create table [album].
         */
        $table_album['column'] = array(
            $COLUMNS['id'],
            $COLUMNS['title'],
            $COLUMNS['keywords'],
            $COLUMNS['description'],
            $COLUMNS['thumb_id'],
            $COLUMNS['text'],
            $COLUMNS['source_id'],
            $COLUMNS['category_id'],
            $COLUMNS['create_time'],
        );
        $table_album['constraint'] = array(
            $CONSTRAINTS['id_primarykey']
        );
        $this->create_table( 'album', $table_album);
        
        
        /**
         * Create table [album_photo].
         */
        $table_album_photo['column'] = array(
            $COLUMNS['album_id'],
            $COLUMNS['photo_id'],
        );
        $this->create_table( 'album_photo', $table_album_photo);

        
        /**
         * Create table [photo].
         */
        $table_photo['column'] = array(
            $COLUMNS['id'],
            $COLUMNS['image'],
            $COLUMNS['text'],
            $COLUMNS['source_id'],
            $COLUMNS['create_time'],
        );
        $table_photo['constraint'] = array(
            $CONSTRAINTS['id_primarykey']
        );
        $this->create_table( 'photo', $table_photo);
        
        
        
        /**
         * Create table [thumb].
         */
        $table_thumb['column'] = array(
            $COLUMNS['id'],
            $COLUMNS['image'],
            $COLUMNS['text'],
            $COLUMNS['source_id'],
            $COLUMNS['create_time'],
        );
        $table_thumb['constraint'] = array(
            $CONSTRAINTS['id_primarykey']
        );
        $this->create_table( 'thumb', $table_thumb);
        
        
        /**
         * Create table [category].
         */
        $table_category['column'] = array(
            $COLUMNS['id'],
            $COLUMNS['type'],
            $COLUMNS['name'],
            $COLUMNS['source_id'],
            new Column\Integer('parent_id',FALSE,NULL),
        );
        $table_category['constraint'] = array(
            $CONSTRAINTS['id_primarykey']
        );
        $this->create_table( 'category', $table_category);
        
    }
    
    /**
     * Insert the base data rows.
     */
    private function insert_rows() {
        
        
    }
    

}

?>