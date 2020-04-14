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
        error_log($cmd . ' ' . $result);

        $data = json_decode($result);

        if (@$data->error) {
            error_response('Api error' . ($data ? ': ' . $data->error : ''));
        }
        return $result;
    }

    private function generateCurlCommand($endpoint, $middle = null)
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

    private function post_json_headers($data)
    {
        $parts = [];

        $parts[] = '-H "Content-Type: application/json"';
        $parts[] = '--request POST';
        $parts[] = "--data '" . json_encode($data) . "'";

        return implode(' ', $parts);
    }

    private function post_headers()
    {
        return '--request POST';
    }

    function search($package, $blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/' . $package . '/blend/' . $blend . '/search' . ($query ? "?{$query}" : '');

        return json_decode($this->execute($endpoint));
    }

    function bulkdelete($package, $blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/' . $package . '/blend/' . $blend . '/delete' . ($query ? "?{$query}" : '');
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function bulkupdate($package, $blend, $data, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/' . $package . '/blend/' . $blend . '/update' . ($query ? "?{$query}" : '');
        $middle = $this->post_json_headers($data);

        return json_decode($this->execute($endpoint, $middle));
    }

    function bulkprint($package, $blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/' . $package . '/blend/' . $blend . '/print' . ($query ? "?{$query}" : '');
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function summaries($package, $blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $endpoint = '/' . $package . '/blend/' . $blend . '/summaries' . ($query ? "?{$query}" : '');

        $results = [];

        foreach (json_decode($this->execute($endpoint), true) as $name => $result) {
            $results[$name] = (object) $result;
        }

        return $results;
    }

    function save($package, $linetype, $line)
    {
        $endpoint = '/' . $package . '/' . $linetype . (@$line->id ? '/' . $line->id : '') . '/save';
        $middle = $this->post_json_headers($line);

        return json_decode($this->execute($endpoint, $middle));
    }

    function delete($package, $linetype, $id)
    {
        $endpoint = '/' . $package . '/' . $linetype . '/' . $id . '/delete';
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function unlink($package, $linetype, $id, $parenttype, $parentid)
    {
        $endpoint = '/' . $package . '/' . $linetype . '/' . $id . '/unlink/' . $parenttype . '/' . $parentid;
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function print($package, $linetype, $id)
    {
        $endpoint = '/' . $package . '/' . $linetype . '/' . $id . '/print';
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function blends($package)
    {
        return json_decode($this->execute("/{$package}/blend/list"));
    }

    function blend($package, $blend)
    {
        return json_decode($this->execute("/{$package}/blend/{$blend}/info"));
    }

    function linetype($package, $linetype)
    {
        return json_decode($this->execute("/{$package}/{$linetype}/info"));
    }

    function tablelink($tablelink)
    {
        return json_decode($this->execute("/tablelink/{$tablelink}/info"));
    }

    function suggested($package, $linetype)
    {
        return json_decode($this->execute("/{$package}/{$linetype}/suggested"), true);
    }

    function get($package, $linetype, $id)
    {
        return json_decode($this->execute("/{$package}/{$linetype}/{$id}"));
    }

    function children($package, $linetype, $childset, $id)
    {
        return json_decode($this->execute("/{$package}/{$linetype}/{$id}/child/{$childset}"));
    }

    function file($file)
    {
        $endpoint = '/file/' . $file;

        return json_decode($this->execute($endpoint));
    }
}
