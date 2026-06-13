<?php

namespace App\Services;

use \Venturecraft\Revisionable\Revision;
use App\Services\CarbonCustom as Carbon;

/**
 * Trait used to override methods fomr the trait Revionable by Venturecraft
 */
trait RevisionsTraitService
{
    use \Venturecraft\Revisionable\RevisionableTrait;

    /**
     * Initializes the identifyBy variable so we know how to identify
     */
    protected $identifyBy = null;

    /**
     * Called after a model is successfully saved.
     *
     * @return void
     */
    public function postSave()
    {
        if (isset($this->historyLimit) && $this->revisionHistory()->count() >= $this->historyLimit) {
            $LimitReached = true;
        } else {
            $LimitReached = false;
        }
        if (isset($this->revisionCleanup)) {
            $RevisionCleanup=$this->revisionCleanup;
        } else {
            $RevisionCleanup=false;
        }

        // check if the model already exists
        if (((!isset($this->revisionEnabled) || $this->revisionEnabled) && $this->updating) && (!$LimitReached || $RevisionCleanup)) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            $revisions = array();

            foreach ($changes_to_record as $key => $change) {
                $revisions[] = array(
                    'revisionable_type' => $this->getMorphClass(),
                    'revisionable_id'   => $this->getKey(),
                    'key'               => $key,
                    'old_value'         => array_get($this->originalData, $key),
                    'new_value'         => $this->updatedData[$key],
                    'user_id'           => $this->getSystemUserId(),
                    'created_at'        => Carbon::now(),
                    'updated_at'        => Carbon::now(),
                    'identity'          => $this->identifyBy,
                    'identityBy'        => $this->identity,
                );
            }

            if (count($revisions) > 0) {
                if($LimitReached && $RevisionCleanup){
                    $toDelete = $this->revisionHistory()->orderBy('id','asc')->limit(count($revisions))->get();
                    foreach($toDelete as $delete){
                        $delete->delete();
                    }
                }
                $revision = new Revision;
                \DB::table($revision->getTable())->insert($revisions);
                \Event::fire('revisionable.saved', array('model' => $this, 'revisions' => $revisions));
            }
        }
    }

    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     *
     * @return array fields with new data, that should be recorded
     */
    private function changedRevisionableFields()
    {
        $changes_to_record = array();
        if (isset($this->identity)
            && array_key_exists($this->identity, $this->updatedData)
            && isset($this->updatedData[$this->identity])
        ) {
            $this->identifyBy = $this->updatedData[$this->identity];
        } else {
            $this->identity = null;
        }

        foreach ($this->dirtyData as $key => $value) {
            // check that the field is revisionable, and double check
            // that it's actually new data in case dirty is, well, clean
            if ($this->isRevisionable($key) && !is_array($value)) {
                if (isset($this->treatDate)
                    && gettype($this->treatDate) === "array"
                    && in_array($key, $this->treatDate)
                    ) {
                    $this->updatedData[$key] = $value . " 00:00:00";
                }

                if (!isset($this->originalData[$key]) || $this->originalData[$key] != $this->updatedData[$key]) {
                    $changes_to_record[$key] = $value;
                }

            } else {
                // we don't need these any more, and they could
                // contain a lot of data, so lets trash them.
                unset($this->updatedData[$key]);
                unset($this->originalData[$key]);
            }
        }

        return $changes_to_record;
    }

    /**
    * Called after record successfully created
    */
    public function postCreate()
    {

        $changes_to_record = array();
        if (isset($this->identity)
            && array_key_exists($this->identity, $this->updatedData)
            && isset($this->updatedData[$this->identity])
        ) {
            $this->identifyBy = $this->updatedData[$this->identity];
        } else {
            $this->identity = null;
        }

        // Check if we should store creations in our revision history
        // Set this value to true in your model if you want to
        if(empty($this->revisionCreationsEnabled))
        {
            // We should not store creations.
            return false;
        }

        if ((!isset($this->revisionEnabled) || $this->revisionEnabled))
        {
            $revisions[] = array(
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id'   => $this->getKey(),
                'key'               => self::CREATED_AT,
                'old_value'         => null,
                'new_value'         => $this->{self::CREATED_AT},
                'user_id'           => $this->getSystemUserId(),
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
                'identity'          => $this->identifyBy,
                'identityBy'        => $this->identity,
            );

            $revision = new Revision;
            \DB::table($revision->getTable())->insert($revisions);
            \Event::fire('revisionable.created', array('model' => $this, 'revisions' => $revisions));
        }

    }

}
