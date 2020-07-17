 <?php
class ApiClient
{
    private $auth;
    private $asuser;
    private $url;
    private $touched;

    function __construct($auth, $url)
    {
        $this->auth = $auth;
        $this->url = $url;
    }

    private function execute($endpoint, $middle = null)
    {
        $cmd = $this->generateCurlCommand($endpoint, $middle);
        $result = shell_exec($cmd);

        if (defined('APIDEBUG') && APIDEBUG) {
            error_log($cmd . ' ' . $result);
        }

        $data = json_decode($result);

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

        if ($middle) {
            $commandparts[] = $middle;
        }

        $commandparts[] = "'" . $this->url . $endpoint . "'";

        return implode(' ', $commandparts);
    }

    private function render_filters($filters)
    {
        $values = [];

        foreach ($filters as $filter) {
            $cmp = @$filter->cmp ?? '=';
            $values[] = urlencode("{$filter->field}{$cmp}{$filter->value}");
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

    function touch()
    {
        if ($this->touched === null) {
            $data = json_decode($this->execute('/touch'));
            $this->touched = is_object($data) && !property_exists($data, 'error');
        }

        return $this->touched;
    }

    function login($username, $password)
    {
        $endpoint = '/auth/login';
        $middle = $this->post_json_headers([
            'username' => $username,
            'password' => $password,
        ]);

        return json_decode($this->execute($endpoint, $middle));
    }

    function logout()
    {
        $endpoint = '/auth/logout';
        $middle = $this->post_headers();

        return json_decode($this->execute($endpoint, $middle));
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

        $results = json_decode($this->execute($endpoint), true);

        if (!@$results->error) {
            foreach ($results as $name => $result) {
                $results[$name] = (object) $result;
            }
        }

        return $results;
    }

    function save($linetype, $data, $keep_filedata = false)
    {
        $endpoint = '/' . $linetype;

        if ($keep_filedata) {
            $endpoint .= '?keepfiledata=1';
        }

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
