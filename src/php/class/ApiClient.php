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

    function save($linetype, $line)
    {
        $endpoint = '/' . $linetype . (@$line->id ? '/' . $line->id : '') . '/save';
        $middle = $this->post_json_headers($line);

        return json_decode($this->execute($endpoint, $middle));
    }

    function delete($linetype, $id)
    {
        $endpoint = '/' . $linetype . '/' . $id . '/delete';
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function unlink($linetype, $id, $parenttype, $parentid)
    {
        $endpoint = '/' . $linetype . '/' . $id . '/unlink/' . $parenttype . '/' . $parentid;
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
    }

    function print($linetype, $id)
    {
        $endpoint = '/' . $linetype . '/' . $id . '/print';
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

    function tablelink($tablelink)
    {
        return json_decode($this->execute("/tablelink/{$tablelink}/info"));
    }

    function suggested($linetype)
    {
        return json_decode($this->execute("/{$linetype}/suggested"), true);
    }

    function get($linetype, $id)
    {
        return json_decode($this->execute("/{$linetype}/{$id}"));
    }

    function children($linetype, $childset, $id)
    {
        return json_decode($this->execute("/{$linetype}/{$id}/child/{$childset}"));
    }

    function file($file)
    {
        $endpoint = '/file/' . $file;

        return json_decode($this->execute($endpoint));
    }
}
