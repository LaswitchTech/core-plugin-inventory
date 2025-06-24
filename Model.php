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

    /**
     * Retrieve Inventory
     *
     * @param int $organization
     * @return array
     */
    public function list(int $organization): array
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table('inventory')
            ->select('*')
            ->join('owner', 'users', 'username')
            ->join('assignedTo', 'users', 'id')
            ->join('vcard', 'vcards', 'id')
            ->join('task', 'tasks', 'id')
            ->join('client', 'clients', 'id')
            ->join('organization', 'organizations', 'id')
            ->order('vcard.name', 'ASC')
            ->filter()
            ->where('organization', $organization)
            ->where('isArchived', 0)
            ->where('id', 9999, '<>');

        // Retrieve the Results
        $result = $Query->result();

        // Decode JSON Fields
        foreach($result as $key => $record){
            $result[$key]['task']['process'] = json_decode($record['task']['process'] ?? '[]', true);
            $result[$key]['vcard']['tags'] = json_decode($record['vcard']['tags'] ?? '[]', true);
            $result[$key]['vcard']['industries'] = json_decode($record['vcard']['industries'] ?? '[]', true);
        }

        // Return the Results
        return $result;
    }

    /**
     * Retrieve Lead's Details
     *
     * @param int $id
     * @return array
     */
    public function get(int $id): array
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table('inventory')
            ->select('*')
            ->join('owner', 'users', 'username')
            ->join('assignedTo', 'users', 'id')
            ->join('vcard', 'vcards', 'id')
            ->join('task', 'tasks', 'id')
            ->join('client', 'clients', 'id')
            ->join('organization', 'organizations', 'id')
            ->order('vcard.name', 'ASC')
            ->filter()
            ->where('id', 9999, '<>')
            ->where('isArchived', 1, '<>')
            ->filter()
            ->where('id', $id)
            ->limit(1);

        // Retrieve the Results
        $result = $Query->result();

        // Decode JSON Fields
        foreach($result as $key => $record){

            // Decode JSON Fields
            $result[$key]['task']['process'] = json_decode($record['task']['process'] ?? '[]', true);
            $result[$key]['vcard']['tags'] = json_decode($record['vcard']['tags'] ?? '[]', true);
            $result[$key]['vcard']['industries'] = json_decode($record['vcard']['industries'] ?? '[]', true);

            // Retrieve the vcard's avatar
            if($record['vcard']['avatar']){
                $Query = $this->Database->query()
                    ->table('files')
                    ->select('*')
                    ->where('id', $record['vcard']['avatar'])
                    ->limit(1);
                $result[$key]['vcard']['avatar'] = $Query->fetch()[0] ?? $record['vcard']['avatar'];
            }

            // Retrieve the Events
            $Query = $this->Database->query()
                ->table('events')
                ->select('*')
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->index('id');
            $result[$key]['events'] = $Query->result();
            $Query = $this->Database->query()
                ->table('events')
                ->select('*')
                ->where('targetTable', 'vcards')
                ->where('targetId', $record['vcard']['id'])
                ->index('id');
            $result[$key]['events'] = array_merge($result[$key]['events'], $Query->result());

            // Retrieve the Notes
            $Query = $this->Database->query()
                ->table('notes')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->where('isArchived', 1, '<>')
                ->index('id');
            $result[$key]['notes'] = $Query->result();
            $Query = $this->Database->query()
                ->table('notes')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->where('targetTable', 'vcards')
                ->where('targetId', $record['vcard']['id'])
                ->where('isArchived', 1, '<>')
                ->index('id');
            foreach($Query->result() as $note){
                $result[$key]['notes'][$note['id']] = $note;
                $result[$key]['notes'][$note['id']]['sharedWith'] = json_decode($note['sharedWith'] ?? '[]', true);
            }

            // Retrieve the Contacts
            $Query = $this->Database->query()
                ->table('contacts')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->join('vcard', 'vcards', 'id')
                ->filter()
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->where('isArchived', 1, '<>')
                ->filter('OR')
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'clients')
                ->where('targetId', $record['client']['id'])
                ->where('isArchived', 1, '<>')
                ->index('id');
            $result[$key]['contacts'] = $Query->result();

            // Retrieve the Files
            $Query = $this->Database->query()
                ->table('files')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->filter()
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->where('isArchived', 1, '<>')
                ->filter('OR')
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'clients')
                ->where('targetId', $record['client']['id'])
                ->where('isArchived', 1, '<>')
                ->index('id');
            $result[$key]['files'] = $Query->result();

            // Retrieve the Documents
            $Query = $this->Database->query()
                ->table('documents')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->join('doctype', 'doctypes', 'id')
                ->join('organization', 'organizations', 'id')
                ->join('letterhead', 'files', 'id')
                ->filter()
                ->where('isArchived', 1, '<>')
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->filter('OR')
                ->where('isArchived', 1, '<>')
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'clients')
                ->where('targetId', $record['client']['id'])
                ->index('id');
            $result[$key]['documents'] = $Query->result();

            // Retrieve the Follow-ups
            $result[$key]['followups'] = [];
            $Query = $this->Database->query()
                ->table('followups')
                ->select('*')
                ->join('owner', 'users', 'username')
                ->join('assignedTo', 'users', 'id')
                ->join('task', 'tasks', 'id')
                ->join('vcard', 'vcards', 'id')
                ->join('organization', 'organizations', 'id')
                ->filter()
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'inventory')
                ->where('targetId', $record['id'])
                ->where('isArchived', 1, '<>')
                ->filter('OR')
                ->where('organization', $record['organization']['id'])
                ->where('targetTable', 'clients')
                ->where('targetId', $record['client']['id'])
                ->where('isArchived', 1, '<>')
                ->index('id');
            foreach($Query->result() as $followup){

                // Decode JSON Fields
                $followup['task']['process'] = json_decode($followup['task']['process'] ?? '[]', true);
                $followup['vcard']['tags'] = json_decode($followup['vcard']['tags'] ?? '[]', true);
                $followup['vcard']['industries'] = json_decode($followup['vcard']['industries'] ?? '[]', true);
                $result[$key]['followups'][$followup['id']] = $followup;
            }
        }

        // Return the Results
        return $result[array_key_first($result)] ?? [];
    }

    /**
     * Create a new lead and return the id
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table('inventory')
            ->insert($data);

        // Execute the Query
        $affectedRows = $Query->execute();

        // Execute the Query
        return $Query->lastId();
    }

    /**
     * Update a lead
     *
     * @param int $id
     * @param array $data
     * @return int
     */
    public function update(int $id, array $data): int
    {
        // Create the Query
        $Query = $this->Database->query()
            ->table('inventory')
            ->update($data)
            ->where('id', $id);

        // Execute the Query
        return $Query->execute();
    }
}
