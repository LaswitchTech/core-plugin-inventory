<?php

/**
 * Core Framework - InventoryModel
 *
 * @license    MIT (https://mit-license.org/)
 * @author     Louis Ouellet <louis@laswitchtech.com>
 */

// Import additionnal class into the global namespace
use \LaswitchTech\Core\Abstracts\Model;

class InventoryModel extends Model {

    // Global Properties
    private $Auth;

    // Properties
    private $table = 'inventories';
    private $primary = 'id';
    private $schema;
    private $definition;
    private $definitions = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Import Global Variables
        global $AUTH;

        // Configure the Global Properties
        $this->Auth = $AUTH;

        // Call the parent constructor
        parent::__construct();

        // Create the Schema
        $this->schema = $this->Database->schema()->define($this->table);

        // Describe the table
        foreach($this->schema->describe() as $column){
            $this->definition[$column['Field']] = $column;
        }
    }

    /**
     * Create a new record and return the id
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Set the Owner
        if(array_key_exists('owner',$this->definition)){
            $data['owner'] = $this->Auth->user()->username;
        }

        // Set the Owner
        if(array_key_exists('organization',$this->definition)){
            $data['organization'] = $this->Auth->user()->organization()->id;
        }

        // Sanitize the Data
        foreach($data as $key => $value){

            // Check if the key exists in the definition
            if(!array_key_exists($key, $this->definition)){

                // Remove the key from the data
                unset($data[$key]);
                continue;
            }

            // Check if the value is an array and encode it as JSON
            if(is_array($value) && !array_key_exists('targetTable', $data) && !array_key_exists('targetId', $data)){
                $data[$key] = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->insert($data);

        // Execute the Query
        $affectedRows = $Query->execute();

        // Execute the Query
        return $Query->lastId();
    }

    /**
     * Retrieve a single record by ID
     *
     * @param int $id
     * @return array
     */
    private function read(string $table, int $id): array
    {
        // Check if the definition is already cached
        if(!array_key_exists($table, $this->definitions)){

            // Create the Schema
            $this->definitions[$table] = [];

            // Describe the table
            foreach($this->Database->schema()->define($table)->describe() as $column){
                $this->definitions[$table][$column['Field']] = $column;
            }
        }

        // Create the Query
        $Query = $this->Database->query()
            ->table($table)
            ->select('*')
            ->where('id', $id)
            ->limit(1);

        // Check if the table has a particular column and join if necessary
        if(array_key_exists('organization', $this->definitions[$table])){
            $Query->join('organization', 'organizations', 'id');
        }
        if(array_key_exists('lead', $this->definitions[$table])){
            $Query->join('lead', 'leads', 'id');
        }
        if(array_key_exists('client', $this->definitions[$table])){
            $Query->join('client', 'clients', 'id');
        }
        if(array_key_exists('vcard', $this->definitions[$table])){
            $Query->join('vcard', 'vcards', 'id');
        }
        if(array_key_exists('task', $this->definitions[$table])){
            $Query->join('task', 'tasks', 'id');
        }

        // Retrieve the Record
        $record = $Query->fetch();

        // Check if the Record exists
        if($record){

            // Set the record to the first element
            $record= $record[array_key_first($record)];

            // Check if a target is set
            if(array_key_exists('targetTable', $record) && array_key_exists('targetId', $record)){

                // Retrieve the Target
                $record['target'] = $this->read($record['targetTable'], $record['targetId']);
            }
        }

        // Return an empty array if not found
        return $record;
    }

    /**
     * Update a record
     *
     * @param int $id
     * @param array $data
     * @return int
     */
    public function update(int $id, array $data): int
    {
        // Sanitize the Data
        foreach($data as $key => $value){

            // Check if the key exists in the definition
            if(!array_key_exists($key, $this->definition)){

                // Remove the key from the data
                unset($data[$key]);
                continue;
            }

            // Skip the owner and organization fields
            if(in_array($key, ['owner', 'organization'])){

                // Remove the key from the data
                unset($data[$key]);
                continue;
            }

            // Check if the value is an array and encode it as JSON
            if(is_array($value) && !array_key_exists('targetTable', $data) && !array_key_exists('targetId', $data)){
                $data[$key] = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->update($data)
            ->where($this->primary, $id);

        // Execute the Query
        return $Query->execute();
    }

    /**
     * Delete a record
     *
     * @param int $id
     * @return int
     */
    public function delete(int $id): int
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->delete()
            ->where($this->primary, $id);

        // Execute the Query
        return $Query->execute();
    }

    /**
     * Retrieve the count of records
     *
     * @param array $conditions
     * @return int
     */
    public function count(array $conditions = [], string $conjunction = 'AND'): int
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->select($this->primary)
            ->where('id', 9999, '<>')
            ->where('organization', $this->Auth->user()->organization()->id);

        // Add the Conditions
        foreach($conditions as $condition){
            $Query->where($condition["key"], $condition["value"], $condition["operator"], $conjunction);
        }

        // Execute the Query
        $records = $Query->fetch();

        // Return the Count
        return count($records);
    }

    /**
     * Retrieve multiple records
     *
     * @param array $conditions
     * @return array
     */
    public function fetchAll(array $conditions = [], string $conjunction = 'AND'): array
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->select('*')
            ->join('owner', 'users', 'username')
            ->join('product', 'products', 'id')
            ->join('organization', 'organizations', 'id')
            ->filter()
            ->where('id', 9999, '<>')
            ->where('isArchived', 1, '<>')
            ->where('organization', $this->Auth->user()->organization()->id);

        // Check if the conditions are empty
        if(!empty($conditions)){

            // Add a Filter
            $Query->filter();

            // Add the Conditions
            foreach($conditions as $condition){
                $Query->where($condition["key"], $condition["value"], $condition["operator"], $conjunction);
            }
        }

        // Retrieve the Results
        $records = $Query->fetch();

        // Loop through the records to process them
        foreach($records as $key => $record){

            // Decode JSON Fields
            $record['commissions'] = json_decode($record['commissions'] ?? '[]', true);

            // Loop through the commissions to fetch the users
            if(array_key_exists('commissions', $record) && is_array($record['commissions'])){
                foreach($record['commissions'] as $index => $commission){
                    if(array_key_exists('user', $commission)){
                        $record['commissions'][$index]['user'] = $this->read('users', $commission['user']);
                    }
                }
            }

            // Retrieve the Target
            if(array_key_exists('targetTable', $record)){
                $record['target'] = $this->read($record['targetTable'], $record['targetId']);
            }

            // Overwrite the record with the processed one
            $records[$key] = $record;
        }

        // Return the Results
        return $records;
    }

    /**
     * Retrieve a single record
     *
     * @param int $id
     * @return array
     */
    public function fetch(int $id): array
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table($this->table)
            ->select('*')
            ->join('owner', 'users', 'username')
            ->join('product', 'products', 'id')
            ->join('organization', 'organizations', 'id')
            ->filter()
            ->where('id', 9999, '<>')
            ->filter()
            ->where($this->primary, $id)
            ->limit(1);

        // Retrieve the record
        $records = $Query->fetch();

        // Loop through the records to process them
        foreach($records as $key => $record){

            // Decode JSON Fields
            $record['commissions'] = json_decode($record['commissions'] ?? '[]', true);

            // Loop through the commissions to fetch the users
            if(array_key_exists('commissions', $record) && is_array($record['commissions'])){
                foreach($record['commissions'] as $index => $commission){
                    if(array_key_exists('user', $commission)){
                        $record['commissions'][$index]['user'] = $this->read('users', $commission['user']);
                    }
                }
            }

            // Retrieve the Target
            if(array_key_exists('targetTable', $record)){
                $record['target'] = $this->read($record['targetTable'], $record['targetId']);
            }

            // Overwrite the record with the processed one
            $records[$key] = $record;
        }

        // Return the record or an empty array if not found
        return $records[array_key_first($records)] ?? [];
    }

    /**
     * Archive a record
     *
     * @param int $id
     * @return int
     */
    public function archive(int $id): int
    {
        // Execute the Query
        return $this->update($id, ['isArchived' => 1]);
    }

    /**
     * Restore a record
     *
     * @param int $id
     * @return int
     */
    public function restore(int $id): int
    {
        // Execute the Query
        return $this->update($id, ['isArchived' => 0]);
    }
}
