<?php

/**
 *
 */
class EntryQueryPagesAdapter extends EntryQueryListAdapter
{
    public function getFilterColumns()
    {
        return ['title', 'handle', 'page_id'];
    }

    public function getSortColumns()
    {
        return ['handle'];
    }
}
