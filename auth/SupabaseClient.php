<?php

class SupabaseClient {
    private $auth;
    private $currentQuery;

    public function __construct($auth) {
        $this->auth = $auth;
    }

    public function from($table) {
        $this->currentQuery = new QueryBuilder($this->auth, $table);
        return $this->currentQuery;
    }

    public function rpc($function, $params = []) {
        return $this->auth->executeQuery('POST', '/rest/v1/rpc/' . $function, [
            'json' => $params
        ]);
    }
}

class QueryBuilder {
    private $auth;
    private $table;
    private $select = '*';
    private $filters = [];
    private $order = [];
    private $limit = null;
    private $offset = null;
    private $count = null;

    public function __construct($auth, $table) {
        $this->auth = $auth;
        $this->table = $table;
    }

    public function select($columns, $options = []) {
        $this->select = is_array($columns) ? implode(',', $columns) : $columns;
        $this->count = isset($options['count']) ? $options['count'] : null;
        return $this;
    }

    public function eq($column, $value) {
        $this->filters[] = [$column, 'eq', $value];
        return $this;
    }

    public function gt($column, $value) {
        $this->filters[] = [$column, 'gt', $value];
        return $this;
    }

    public function lt($column, $value) {
        $this->filters[] = [$column, 'lt', $value];
        return $this;
    }

    public function gte($column, $value) {
        $this->filters[] = [$column, 'gte', $value];
        return $this;
    }

    public function lte($column, $value) {
        $this->filters[] = [$column, 'lte', $value];
        return $this;
    }

    public function like($column, $pattern) {
        $this->filters[] = [$column, 'like', $pattern];
        return $this;
    }

    public function ilike($column, $pattern) {
        $this->filters[] = [$column, 'ilike', $pattern];
        return $this;
    }

    public function is($column, $value) {
        $this->filters[] = [$column, 'is', $value];
        return $this;
    }

    public function in($column, $values) {
        $this->filters[] = [$column, 'in', '(' . implode(',', $values) . ')'];
        return $this;
    }

    public function orderBy($column, $ascending = true) {
        $this->order[] = $column . ($ascending ? '.asc' : '.desc');
        return $this;
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function single() {
        $this->limit = 1;
        return $this;
    }

    private function buildQuery() {
        $query = '/rest/v1/' . $this->table;
        $params = [];

        if ($this->select !== '*') {
            $params['select'] = $this->select;
        }

        foreach ($this->filters as $filter) {
            list($column, $operator, $value) = $filter;
            $params[$column] = $operator . '.' . $value;
        }

        if (!empty($this->order)) {
            $params['order'] = implode(',', $this->order);
        }

        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }

        if ($this->offset !== null) {
            $params['offset'] = $this->offset;
        }

        if ($this->count !== null) {
            $params['count'] = $this->count === 'exact' ? 'exact' : 'planned';
        }

        if (!empty($params)) {
            $query .= '?' . http_build_query($params);
        }

        return $query;
    }

    public function execute() {
        try {
            $query = $this->buildQuery();
            $result = $this->auth->executeQuery('GET', $query);
            
            if ($this->count !== null) {
                return (object)[
                    'data' => $result,
                    'count' => intval($result[0]['count'] ?? 0)
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Query execution error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function insert($data) {
        return $this->auth->executeQuery('POST', '/rest/v1/' . $this->table, [
            'json' => $data
        ]);
    }

    public function update($data) {
        $query = $this->buildQuery();
        return $this->auth->executeQuery('PATCH', $query, [
            'json' => $data
        ]);
    }

    public function delete() {
        $query = $this->buildQuery();
        return $this->auth->executeQuery('DELETE', $query);
    }
}
