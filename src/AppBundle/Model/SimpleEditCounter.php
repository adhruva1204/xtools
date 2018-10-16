<?php
/**
 * This file contains only the SimpleEditCounter class.
 */

declare(strict_types = 1);

namespace AppBundle\Model;

/**
 * A SimpleEditCounter provides basic edit count stats about a user.
 * This class is too 'simple' to bother with tests, we just get
 * the results of the query and return it.
 * @codeCoverageIgnore
 */
class SimpleEditCounter extends Model
{
    /** @var array The Simple Edit Counter results. */
    protected $data = [
        'userId' => null,
        'deletedEditCount' => 0,
        'liveEditCount' => 0,
        'userGroups' => [],
        'globalUserGroups' => [],
    ];

    /**
     * Constructor for the SimpleEditCounter class.
     * @param Project $project
     * @param User $user
     * @param string $namespace Namespace ID or 'all'.
     * @param false|int $start As Unix timestamp.
     * @param false|int $end As Unix timestamp.
     */
    public function __construct(Project $project, User $user, string $namespace = 'all', $start = false, $end = false)
    {
        $this->project = $project;
        $this->user = $user;
        $this->namespace = '' == $namespace ? 0 : $namespace;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Fetch the data from the database and API,
     * then set class properties with the values.
     */
    public function prepareData(): void
    {
        $results = $this->getRepository()->fetchData(
            $this->project,
            $this->user,
            $this->namespace,
            $this->start,
            $this->end
        );

        // Iterate over the results, putting them in the right variables
        foreach ($results as $row) {
            switch ($row['source']) {
                case 'id':
                    $this->data['userId'] = (int)$row['value'];
                    break;
                case 'arch':
                    $this->data['deletedEditCount'] = (int)$row['value'];
                    break;
                case 'rev':
                    $this->data['liveEditCount'] = (int)$row['value'];
                    break;
                case 'groups':
                    $this->data['userGroups'][] = $row['value'];
                    break;
            }
        }

        if (!$this->user->isAnon()) {
            $this->data['globalUserGroups'] = $this->user->getGlobalUserRights($this->project);
        }
    }

    /**
     * Get back all the data as a single associative array.
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the user's ID.
     * @return int
     */
    public function getUserId(): int
    {
        return $this->data['userId'];
    }

    /**
     * Get the number of deleted edits.
     * @return int
     */
    public function getDeletedEditCount(): int
    {
        return $this->data['deletedEditCount'];
    }

    /**
     * Get the number of live edits.
     * @return int
     */
    public function getLiveEditCount(): int
    {
        return $this->data['liveEditCount'];
    }

    /**
     * Get the total number of edits.
     * @return int
     */
    public function getTotalEditCount(): int
    {
        return $this->data['deletedEditCount'] + $this->data['liveEditCount'];
    }

    /**
     * Get the local user groups.
     * @return string[]
     */
    public function getUserGroups(): array
    {
        return $this->data['userGroups'];
    }

    /**
     * Get the global user groups.
     * @return string[]
     */
    public function getGlobalUserGroups(): array
    {
        return $this->data['globalUserGroups'];
    }
}
