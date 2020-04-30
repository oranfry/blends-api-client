<?php
class ApiClient
{
    private $auth;
    private $host;
    private $ip;

    function __construct($auth, $host, $ip = '127.0.0.1')
    {
        $this->auth = $auth;
        $this->host = $host;
        $this->ip = $ip;
    }

    private function execute($endpoint, $middle = null)
    {
        $cmd = $this->generateCurlCommand($endpoint, $middle);
        $result = shell_exec($cmd);

        if (defined('APIDEBUG') && APIDEBUG) {
            error_log($cmd . ' ' . $result);
        }

        $data = json_decode($result);

        if (@$data->error) {
            error_response('Api error (' . $endpoint . ')' . ($data ? ': ' . $data->error : ''));
        }
        return $result;
    }

    public function generateCurlCommand($endpoint, $middle = null)
    {
        if (!preg_match('@^/@', $endpoint)) {
            error_log('Endpoint should start with /');
        }

        $commandparts = [];

        $commandparts[] = 'curl';
        $commandparts[] = '-s';
        $commandparts[] = '-H "X-Auth: ' . $this->auth . '"';

        if ($this->ip) {
            $commandparts[] = '-H "Host: ' . $this->host . '"';
        }

        if ($middle) {
            $commandparts[] = $middle;
        }

        $commandparts[] = "'" . 'http://' . ($this->ip ?? $this->host) . $endpoint . "'";

        return implode(' ', $commandparts);
    }

    private function render_filters($filters)
    {
        $values = [];

        foreach ($filters as $filter) {
            $values[] = urlencode("{$filter->field}{$filter->cmp}{$filter->value}");
        }

        return implode('&', $values);
    }

    public function post_json_headers($data, $method = 'POST')
    {
        $parts = [];

        $parts[] = '-H "Content-Type: application/json"';
        $parts[] = '--request ' . $method;
        $parts[] = "--data '" . json_encode($data) . "'";

        return implode(' ', $parts);
    }

    private function post_headers()
    {
        return '--request POST';
    }

    function search($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/blend/' . $blend . '/search' . ($query ? "?{$query}" : '');

        return json_decode($this->execute($endpoint));
    }

    function bulkdelete($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/blend/' . $blend . '/delete' . ($query ? "?{$query}" : '');
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function bulkupdate($blend, $data, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/blend/' . $blend . '/update' . ($query ? "?{$query}" : '');
        $middle = $this->post_json_headers($data);

        return json_decode($this->execute($endpoint, $middle));
    }

    function bulkprint($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/blend/' . $blend . '/print' . ($query ? "?{$query}" : '');
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function summaries($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/blend/' . $blend . '/summaries' . ($query ? "?{$query}" : '');

        $results = [];

        foreach (json_decode($this->execute($endpoint), true) as $name => $result) {
            $results[$name] = (object) $result;
        }

        return $results;
    }

    function save($linetype, $data)
    {
        $endpoint = '/' . $linetype;
        $middle = $this->post_json_headers($data);

        return json_decode($this->execute($endpoint, $middle));
    }

    function delete($linetype, $id)
    {
        $endpoint = '/' . $linetype . '/delete?id=' . $id;
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function unlink($linetype, $id, $parent)
    {
        $endpoint = '/' . $linetype . '/unlink';
        $line = (object) [
            'id' => $id,
            'parent' => $parent,
        ];

        $middle = $this->post_json_headers([$line]);

        return json_decode($this->execute($endpoint, $middle));
    }

    function print($linetype, $id)
    {
        $endpoint = '/' . $linetype . '/print?id=' . $id;
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function blends()
    {
        return json_decode($this->execute("/blend/list"));
    }

    function blend($blend)
    {
        return json_decode($this->execute("/blend/{$blend}/info"));
    }

    function linetype($linetype)
    {
        return json_decode($this->execute("/{$linetype}/info"));
    }

    function suggested($linetype)
    {
        return json_decode($this->execute("/{$linetype}/suggested"), true);
    }

    function get($linetype, $id)
    {
        return json_decode($this->execute("/{$linetype}/{$id}"));
    }

    function html($linetype, $id)
    {
        return $this->execute("/{$linetype}/{$id}/html");
    }

    function pdf($linetype, $id)
    {
        return $this->execute("/{$linetype}/{$id}/pdf");
    }

    function file($file)
    {
        $endpoint = '/file/' . $file;

        return json_decode($this->execute($endpoint));
    }

    function download($file)
    {
        return $this->execute("/download/{$file}");
    }
}
