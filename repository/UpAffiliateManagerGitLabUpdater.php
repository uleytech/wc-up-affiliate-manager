<?php

/**
 * Class UpAffiliateManagerGitLabUpdater
 */
class UpAffiliateManagerGitLabUpdater
{
    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $repo; // repo name
    private $pluginFile; // __FILE__ of our plugin
    private $repoAPIResult; // holds data from repo
    private $accessToken; // private repo token

    /**
     * ScaGitLabUpdater constructor.
     * @param $pluginFile
     * @param $repoUsername
     * @param $repoProjectName
     * @param string $accessToken
     */
    function __construct($pluginFile, $repoUsername, $repoProjectName, $accessToken = '')
    {
        add_filter("pre_set_site_transient_update_plugins", array($this, "setTransitent"));
        add_filter("plugins_api", array($this, "setPluginInfo"), 10, 3);
        add_filter("upgrader_post_install", array($this, "postInstall"), 10, 3);

        $this->pluginFile = $pluginFile;
        $this->repo = urlencode($repoUsername . '/' . $repoProjectName);
        $this->accessToken = $accessToken;
    }

    // Get information regarding our plugin from WordPress
    private function initPluginData()
    {
        $this->slug = plugin_basename($this->pluginFile);
        $this->pluginData = get_plugin_data($this->pluginFile);
    }

    // Get information regarding our plugin from repo
    private function getRepoReleaseInfo()
    {
        // Only do this once
        if (!empty($this->repoAPIResult)) {
            return;
        }
        // Query the GitHub API
        $url = "https://gitlab.com/api/v4/projects/{$this->repo}/repository/tags/";

        // We need the access token for private repos
        if (!empty($this->accessToken)) {
            $url = add_query_arg(array("private_token" => $this->accessToken), $url);
        }

        // Get the results
        $this->repoAPIResult = wp_remote_retrieve_body(wp_remote_get($url));
        if (!empty($this->repoAPIResult)) {
            $this->repoAPIResult = @json_decode($this->repoAPIResult);
        }
        // Use only the latest release
        if (is_array($this->repoAPIResult)) {
            $this->repoAPIResult = $this->repoAPIResult[0];
        }
    }

    // Push in plugin version information to get the update notification
    public function setTransitent($transient)
    {
        // If we have checked the plugin data before, don't re-check
        if (empty($transient->checked)) {
            return $transient;
        }
        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        // Check the versions if we need to do an update
        $doUpdate = version_compare($this->repoAPIResult->name, $transient->checked[$this->slug]);

        // Update the transient to include our updated plugin data
        if ($doUpdate == 1) {
            $package = "https://gitlab.com/api/v4/projects/{$this->repo}/repository/archive.zip?sha={$this->repoAPIResult->name}";

            // Include the access token for private GitHub repos
            if (!empty($this->accessToken)) {
                $package = add_query_arg(array("private_token" => $this->accessToken), $package);
            }

            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $this->repoAPIResult->name;
            $obj->url = $this->pluginData["PluginURI"];
            $obj->package = $package;
            $transient->response[$this->slug] = $obj;
        }
        return $transient;
    }

    // Push in plugin version information to display in the details lightbox
    public function setPluginInfo($false, $action, $response)
    {
        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        // If nothing is found, do nothing
        if (empty($response->slug) || $response->slug != $this->slug) {
            return false;
        }

        // Add our plugin information
        $response->last_updated = $this->repoAPIResult->commit->created_at;
        $response->slug = $this->slug;
        $response->plugin_name = $this->pluginData["Name"];
        $response->version = $this->repoAPIResult->name;
        $response->author = $this->pluginData["AuthorName"];
        $response->homepage = $this->pluginData["PluginURI"];

        // This is our release download zip file
        $downloadLink = "https://gitlab.com/api/v4/projects/{$this->repo}/repository/archive.zip?sha={$this->repoAPIResult->name}";

        // Include the access token for private GitHub repos
        if (!empty($this->accessToken)) {
            $downloadLink = add_query_arg(
                array("access_token" => $this->accessToken),
                $downloadLink
            );
        }
        $response->download_link = $downloadLink;

        // Create tabs in the lightbox
        $response->sections = array(
            'description' => $this->pluginData["Description"],
            'changelog' => class_exists("Parsedown")
                ? Parsedown::instance()->parse($this->repoAPIResult->release->description)
                : $this->repoAPIResult->release->description
        );

        // Gets the required version of WP if available
        $matches = null;
        preg_match("/requires:\s([\d\.]+)/i", $this->repoAPIResult->release->description, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->requires = $matches[1];
                }
            }
        }

        // Gets the tested version of WP if available
        $matches = null;
        preg_match("/tested:\s([\d\.]+)/i", $this->repoAPIResult->release->description, $matches);
        if (!empty($matches)) {
            if (is_array($matches)) {
                if (count($matches) > 1) {
                    $response->tested = $matches[1];
                }
            }
        }
        return $response;
    }

    // Perform additional actions to successfully install our plugin
    public function postInstall($true, $hook_extra, $result)
    {
        // Get plugin information
        $this->initPluginData();

        // Remember if our plugin was previously activated
        $wasActivated = is_plugin_active($this->slug);

        // Since we are hosted in GitHub, our plugin folder would have a dirname of
        // reponame-tagname change it to our original one:
        global $wp_filesystem;
        $pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->slug);
        $wp_filesystem->move($result['destination'], $pluginFolder);
        $result['destination'] = $pluginFolder;

        // Re-activate plugin if needed
        if ($wasActivated) {
            $activate = activate_plugin($this->slug);
        }

        return $result;
    }
}