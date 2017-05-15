<?php

namespace Xtools;

use \DateTime;

/**
 * An EditCounter provides statistics about a user's edits on a project.
 */
class EditCounter extends Model
{
    
    /** @var Project */
    protected $project;
    
    /** @var User */
    protected $user;

    /** @var int[] */
    protected $revisionCounts;

    /** @var string[] */
    protected $revisionDates;

    /** @var int[] */
    protected $pageCounts;
    
    /** @var int[] */
    protected $logCounts;

    public function __construct(Project $project, User $user)
    {
        $this->project = $project;
        $this->user = $user;
    }

    /**
     * Get revision count data.
     * @return int[]
     */
    protected function getRevisionCounts()
    {
        if (! is_array($this->revisionCounts)) {
            $this->revisionCounts = $this->getRepository()
                ->getRevisionCounts($this->project, $this->user);
        }
        return $this->revisionCounts;
    }

    /**
     * Get revision dates.
     * @return int[]
     */
    protected function getRevisionDates()
    {
        if (! is_array($this->revisionDates)) {
            $this->revisionDates = $this->getRepository()
                ->getRevisionDates($this->project, $this->user);
        }
        return $this->revisionDates;
    }

    /**
     * Get page count data.
     * @return int[]
     */
    protected function getPageCounts()
    {
        if (! is_array($this->pageCounts)) {
            $this->pageCounts = $this->getRepository()
                ->getPageCounts($this->project, $this->user);
        }
        return $this->pageCounts;
    }

    /**
     * Get revision dates.
     * @return int[]
     */
    protected function getLogCounts()
    {
        if (! is_array($this->logCounts)) {
            $this->logCounts = $this->getRepository()
                ->getLogCounts($this->project, $this->user);
        }
        return $this->logCounts;
    }

    public function countLiveRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['live']) ? $revCounts['live'] : 0;
    }

    /**
     * Get the total number of revisions that have been deleted.
     * @return int
     */
    public function countDeletedRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['deleted']) ? $revCounts['deleted'] : 0;
    }

    /**
     * Get the total edit count (live + deleted).
     * @return int
     */
    public function countAllRevisions()
    {
        return $this->countLiveRevisions() + $this->countDeletedRevisions();
    }

    /**
     * Get the total number of revisions with comments.
     * @return int
     */
    public function countRevisionsWithComments()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['with_comments']) ? $revCounts['with_comments'] : 0;
    }

    /**
     * Get the total number of revisions without comments.
     * @return int
     */
    public function countRevisionsWithoutComments()
    {
        return $this->countAllRevisions() - $this->countRevisionsWithComments();
    }

    /**
     * Get the total number of revisions marked as 'minor' by the user.
     * @return int
     */
    public function countMinorRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['minor']) ? $revCounts['minor'] : 0;
    }

    /**
     * Get the total number of revisions under 20 bytes.
     */
    public function countSmallRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['small']) ? $revCounts['small'] : 0;
    }

    /**
     * Get the total number of revisions over 1000 bytes.
     */
    public function countLargeRevisions()
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts['large']) ? $revCounts['large'] : 0;
    }

    /**
     * Get the total number of non-deleted pages edited by the user.
     * @return int
     */
    public function countLivePagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-total']) ? $pageCounts['edited-total'] : 0;
    }

    public function countDeletedPagesEdited()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['edited-total']) ? $pageCounts['edited-total'] : 0;
    }

    /**
     * Get the total number of pages ever edited by this user (both live and deleted).
     * @return int
     */
    public function countAllPagesEdited()
    {
        return $this->countLivePagesEdited() + $this->countDeletedPagesEdited();
    }

    /**
     * Get the total number of semi-automated edits.
     * @return int
     */
    public function countAutomatedEdits()
    {
    }

    /**
     * Get the total number of pages (both still live and those that have been deleted) created
     * by the user.
     * @return int
     */
    public function countPagesCreated()
    {
        return $this->countCreatedPagesLive() + $this->countPagesCreatedDeleted();
    }

    /**
     * Get the total number of pages created by the user, that have not been deleted.
     * @return int
     */
    public function countCreatedPagesLive()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-live']) ? (int)$pageCounts['created-live'] : 0;
    }
    
    /**
     * Get the total number of pages created by the user, that have since been deleted.
     * @return int
     */
    public function countPagesCreatedDeleted()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['created-deleted']) ? (int)$pageCounts['created-deleted'] : 0;
    }

    /**
     * Get the total number of pages moved by the user.
     * @return int
     */
    public function countPagesMoved()
    {
        $pageCounts = $this->getPageCounts();
        return isset($pageCounts['moved']) ? (int)$pageCounts['moved'] : 0;
    }

    /**
     * Get the average number of edits per page (including deleted revisions and pages).
     * @return float
     */
    public function averageRevisionsPerPage()
    {
        return round($this->countAllRevisions() / $this->countAllPagesEdited(), 2);
    }

    /**
     * Average number of edits made per day.
     * @return float
     */
    public function averageRevisionsPerDay()
    {
        return round($this->countAllRevisions() / $this->getDays(), 2);
    }
    
    /**
     * Get the total number of edits made by the user with semi-automating tools.
     * @TODO
     */
    public function countAutomatedRevisions()
    {
        return 0;
    }
    
    /**
     * Get the count of (non-deleted) edits made in the given timeframe to now.
     * @param string $time One of 'day', 'week', 'month', or 'year'.
     * @return int The total number of live edits.
     */
    public function countRevisionsInLast($time)
    {
        $revCounts = $this->getRevisionCounts();
        return isset($revCounts[$time]) ? $revCounts[$time] : 0;
    }

    /**
     * Get the date and time of the user's first edit.
     */
    public function datetimeFirstRevision()
    {
        $first = $this->getRevisionDates()['first'];
        return new DateTime($first);
    }

    /**
     * Get the date and time of the user's first edit.
     * @return DateTime
     */
    public function datetimeLastRevision()
    {
        $last = $this->getRevisionDates()['last'];
        return new DateTime($last);
    }

    /**
     * Get the number of days between the first and last edits.
     * If there's only one edit, this is counted as one day.
     * @return int
     */
    public function getDays()
    {
        $days = $this->datetimeLastRevision()->diff($this->datetimeFirstRevision())->days;
        return $days > 0 ? $days : 1;
    }

    public function countFilesUploaded()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['upload-upload'] ?: 0;
    }

    public function countFilesUploadedCommons()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['files_uploaded_commons'] ?: 0;
    }

    /**
     * Get the total number of revisions the user has sent thanks for.
     * @return int
     */
    public function thanks()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['thanks-thank'] ?: 0;
    }

    /**
     * Get the total number of approvals
     * @return int
     */
    public function approvals()
    {
        $logCounts = $this->getLogCounts();
        $total = $logCounts['review-approve'] +
        (!empty($logCounts['review-approve-a']) ? $logCounts['review-approve-a'] : 0) +
        (!empty($logCounts['review-approve-i']) ? $logCounts['review-approve-i'] : 0) +
        (!empty($logCounts['review-approve-ia']) ? $logCounts['review-approve-ia'] : 0);
        return $total;
    }

    /**
     * @return int
     */
    public function patrols()
    {
        $logCounts = $this->getLogCounts();
        return $logCounts['patrol-patrol'] ?: 0;
    }
}