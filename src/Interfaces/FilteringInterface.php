<?php


namespace StackPagination\Interfaces;


use Cake\ORM\Query;

interface FilteringInterface
{

    /**
     * User filters to work with paginated results
     *
     * Add user search to paginated results
     *
     * This method both prepares the values for the form that
     * is displayed and applies current or save filter requests
     * to the evolving paginated query.
     *
     * The method must
     *  - if there is post/put data
     *      - add the conditions to $query
     *      - put conditions in the session as required by IndexFilterManagementMiddleware
     *  - else if there is a cached condition package
     *      - add the conditions to $query
     *  - set variables to support the search form
     *  - return $query
     *
     * The provided query will be designed to get a seed set
     * to pass to a stacksFor() request on a StacksTable
     *
     * Sessionized conditions have a scope of pages in which they will
     * persist (as defined in the middleware).
     *
     * @param Query $query
     * @return Query
     */
    public function userFilter(Query $query) : Query;

}
