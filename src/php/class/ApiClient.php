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

    private function execute($request)
    {
        if (!preg_match('@^/@', $request->endpoint)) {
            error_log('Endpoint should start with /');
        }


        $ch = curl_init($this->url . $request->endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (!in_array($request->method, ['GET', 'POST'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->method);
        } elseif ($request->data || $request->method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        if ($request->data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request->data));
        }

        $headers = [];

        if ($this->auth) {
            $headers[] = 'X-Auth: ' . $this->auth;
        }

        $headers[] = 'Content-Type: ' . $request->contentType;
        $headers = array_merge($headers, $request->headers);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (defined('APIDEBUG') && APIDEBUG) {
            error_log(var_export($request, true));
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        $result = curl_exec($ch);

        if (defined('APIDEBUG') && APIDEBUG) {
            error_log(var_export($result, true));
        }

        return $result;
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

    function touch()
    {
        if ($this->touched === null) {
            $data = json_decode($this->execute(new ApiRequest('/touch')));
            $this->touched = is_object($data) && !property_exists($data, 'error');
        }

        return $this->touched;
    }

    function login($username, $password)
    {
        $request = new ApiRequest('/auth/login');
        $request->data = (object) [
            'username' => $username,
            'password' => $password,
        ];

        return json_decode($this->execute($request));
    }

    function logout()
    {
        $request = new ApiRequest('/auth/logout');
        $request->method = 'POST';

        return json_decode($this->execute($request));
    }

    function search($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $request = new ApiRequest('/blend/' . $blend . '/search' . ($query ? "?{$query}" : ''));

        return json_decode($this->execute($request));
    }

    function bulkdelete($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $request = new ApiRequest('/blend/' . $blend . '/delete' . ($query ? "?{$query}" : ''));
        $request->method = 'POST';

        return json_decode($this->execute($request));
    }

    function bulkupdate($blend, $data, $filters = [])
    {
        $query = $this->render_filters($filters);
        $request = new ApiRequest('/blend/' . $blend . '/update' . ($query ? "?{$query}" : ''));
        $request->data = $data;

        return json_decode($this->execute($request));
    }

    function bulkprint($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $request = new ApiRequest('/blend/' . $blend . '/print' . ($query ? "?{$query}" : ''));
        $request->method = 'POST';

        return json_decode($this->execute($request));
    }

    function summaries($blend, $filters = [])
    {
        $query = $this->render_filters($filters);
        $request = new ApiRequest('/blend/' . $blend . '/summaries' . ($query ? "?{$query}" : ''));

        $results = json_decode($this->execute($request), true);

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

        $request = new ApiRequest($endpoint);
        $request->data = $data;

        return json_decode($this->execute($request));
    }

    function delete($linetype, $id)
    {
        $request = new ApiRequest('/' . $linetype . '/delete?id=' . $id);
        $request->method = 'POST';

        return json_decode($this->execute($request));
    }

    function unlink($linetype, $id, $parent)
    {
        $line = (object) [
            'id' => $id,
            'parent' => $parent,
        ];

        $request = new ApiRequest('/' . $linetype . '/unlink');
        $request->data = [$line];

        return json_decode($this->execute($request));
    }

    function print($linetype, $id)
    {
        $request = new ApiRequest('/' . $linetype . '/print?id=' . $id);
        $request->method = 'POST';

        return json_decode($this->execute($request));
    }

    function blends()
    {
        return json_decode($this->execute(new ApiRequest("/blend/list")));
    }

    function blend($blend)
    {
        return json_decode($this->execute(new ApiRequest("/blend/{$blend}/info")));
    }

    function linetype($linetype)
    {
        return json_decode($this->execute(new ApiRequest("/{$linetype}/info")));
    }

    function suggested($linetype)
    {
        return json_decode($this->execute(new ApiRequest("/{$linetype}/suggested")), true);
    }

    function get($linetype, $id)
    {
        return json_decode($this->execute(new ApiRequest("/{$linetype}/{$id}")));
    }

    function html($linetype, $id)
    {
        return $this->execute(new ApiRequest("/{$linetype}/{$id}/html"));
    }

    function pdf($linetype, $id)
    {
        return $this->execute(new ApiRequest("/{$linetype}/{$id}/pdf"));
    }

    function file($file)
    {
        $endpoint = '/file/' . $file;
        $request = new ApiRequest($endpoint);

        return json_decode($this->execute($request));
    }

    function download($file)
    {
        return $this->execute(new ApiRequest("/download/{$file}"));
    }
}
