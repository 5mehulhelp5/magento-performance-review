<?php

namespace Performance\Review\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class FrontendAnalyzer
{
    private ScopeConfigInterface $scopeConfig;
    private AssetRepository $assetRepository;
    private Filesystem $filesystem;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        AssetRepository $assetRepository,
        Filesystem $filesystem
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->assetRepository = $assetRepository;
        $this->filesystem = $filesystem;
    }

    public function analyzeFrontend(): array
    {
        $issues = [];

        // Check JavaScript settings
        $jsIssues = $this->checkJavaScriptSettings();
        if (!empty($jsIssues)) {
            $issues = array_merge($issues, $jsIssues);
        }

        // Check CSS settings
        $cssIssues = $this->checkCssSettings();
        if (!empty($cssIssues)) {
            $issues = array_merge($issues, $cssIssues);
        }

        // Check image optimization
        $imageIssues = $this->checkImageOptimization();
        if (!empty($imageIssues)) {
            $issues = array_merge($issues, $imageIssues);
        }

        // Check static content signing
        $signingIssues = $this->checkStaticContentSigning();
        if (!empty($signingIssues)) {
            $issues = array_merge($issues, $signingIssues);
        }

        // Check full page cache
        $fpcIssues = $this->checkFullPageCache();
        if (!empty($fpcIssues)) {
            $issues = array_merge($issues, $fpcIssues);
        }

        // Check CDN configuration
        $cdnIssues = $this->checkCdnConfiguration();
        if (!empty($cdnIssues)) {
            $issues = array_merge($issues, $cdnIssues);
        }

        return $issues;
    }

    private function checkJavaScriptSettings(): array
    {
        $issues = [];

        // Check JS bundling
        $jsBundling = $this->scopeConfig->getValue('dev/js/enable_js_bundling', ScopeInterface::SCOPE_STORE);
        if (!$jsBundling) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Frontend',
                'issue' => 'JavaScript bundling is disabled',
                'details' => 'JS bundling reduces the number of HTTP requests by combining JS files. This significantly improves page load times.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable in Stores > Configuration > Advanced > Developer > JavaScript Settings'
            ];
        }

        // Check JS minification
        $jsMinify = $this->scopeConfig->getValue('dev/js/minify_files', ScopeInterface::SCOPE_STORE);
        if (!$jsMinify) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Frontend',
                'issue' => 'JavaScript minification is disabled',
                'details' => 'Minifying JavaScript files reduces file size and improves load times.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable JS minification in production'
            ];
        }

        // Check JS merging
        $jsMerge = $this->scopeConfig->getValue('dev/js/merge_files', ScopeInterface::SCOPE_STORE);
        if (!$jsMerge) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'JavaScript merging is disabled',
                'details' => 'Merging JS files reduces HTTP requests. Consider enabling if not using HTTP/2.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable if not using HTTP/2'
            ];
        }

        return $issues;
    }

    private function checkCssSettings(): array
    {
        $issues = [];

        // Check CSS minification
        $cssMinify = $this->scopeConfig->getValue('dev/css/minify_files', ScopeInterface::SCOPE_STORE);
        if (!$cssMinify) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Frontend',
                'issue' => 'CSS minification is disabled',
                'details' => 'Minifying CSS files reduces file size and improves load times.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable CSS minification in production'
            ];
        }

        // Check CSS merging
        $cssMerge = $this->scopeConfig->getValue('dev/css/merge_css_files', ScopeInterface::SCOPE_STORE);
        if (!$cssMerge) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'CSS merging is disabled',
                'details' => 'Merging CSS files reduces HTTP requests. Consider enabling if not using HTTP/2.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable if not using HTTP/2'
            ];
        }

        // Check critical CSS
        $criticalCss = $this->scopeConfig->getValue('dev/css/use_css_critical_path', ScopeInterface::SCOPE_STORE);
        if (!$criticalCss) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'Critical CSS is not configured',
                'details' => 'Critical CSS improves perceived performance by inlining above-the-fold styles.',
                'current_value' => 'Not configured',
                'recommended_value' => 'Configure critical CSS for better performance'
            ];
        }

        return $issues;
    }

    private function checkImageOptimization(): array
    {
        $issues = [];

        // Check WebP support
        $webpConfig = $this->scopeConfig->getValue('system/media_gallery/renditions/enabled', ScopeInterface::SCOPE_STORE);
        if (!$webpConfig) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Frontend',
                'issue' => 'WebP image format not enabled',
                'details' => 'WebP images are 25-35% smaller than JPEG/PNG. This significantly reduces bandwidth usage.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable WebP support in Stores > Configuration > General > System'
            ];
        }

        // Check lazy loading
        $lazyLoading = $this->scopeConfig->getValue('cms/pagebuilder/lazy_loading', ScopeInterface::SCOPE_STORE);
        if (!$lazyLoading) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'Image lazy loading not configured',
                'details' => 'Lazy loading delays image loading until needed, improving initial page load.',
                'current_value' => 'Not configured',
                'recommended_value' => 'Enable lazy loading for images'
            ];
        }

        return $issues;
    }

    private function checkStaticContentSigning(): array
    {
        $issues = [];

        $staticSigning = $this->scopeConfig->getValue('dev/static/sign', ScopeInterface::SCOPE_STORE);
        if (!$staticSigning) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'Static content signing is disabled',
                'details' => 'Static content signing enables browser caching with cache busting on updates.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable static content signing in production'
            ];
        }

        return $issues;
    }

    private function checkFullPageCache(): array
    {
        $issues = [];

        // Check if FPC is enabled
        $fpcEnabled = $this->scopeConfig->getValue('system/full_page_cache/caching_application', ScopeInterface::SCOPE_STORE);
        
        if (!$fpcEnabled || $fpcEnabled == '1') {
            $issues[] = [
                'priority' => 'High',
                'category' => 'Frontend',
                'issue' => 'Full Page Cache not using Varnish',
                'details' => 'Varnish cache significantly improves performance for anonymous users.',
                'current_value' => $fpcEnabled == '1' ? 'Built-in cache' : 'Disabled',
                'recommended_value' => 'Use Varnish for full page caching'
            ];
        }

        // Check cache lifetime
        $ttl = $this->scopeConfig->getValue('system/full_page_cache/ttl', ScopeInterface::SCOPE_STORE);
        if ($ttl && $ttl < 86400) {
            $issues[] = [
                'priority' => 'Low',
                'category' => 'Frontend',
                'issue' => 'Short full page cache lifetime',
                'details' => sprintf('Cache TTL is %d seconds. Consider increasing for better performance.', $ttl),
                'current_value' => $ttl . ' seconds',
                'recommended_value' => '86400 seconds (24 hours) or more'
            ];
        }

        return $issues;
    }

    private function checkCdnConfiguration(): array
    {
        $issues = [];

        // Check if CDN is configured for media
        $mediaUrl = $this->scopeConfig->getValue('web/unsecure/base_media_url', ScopeInterface::SCOPE_STORE);
        $staticUrl = $this->scopeConfig->getValue('web/unsecure/base_static_url', ScopeInterface::SCOPE_STORE);
        
        $baseUrl = $this->scopeConfig->getValue('web/unsecure/base_url', ScopeInterface::SCOPE_STORE);
        
        if (!$mediaUrl || $mediaUrl === '{{unsecure_base_url}}media/') {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'CDN not configured for media files',
                'details' => 'Using a CDN for media files reduces server load and improves global performance.',
                'current_value' => 'Local media serving',
                'recommended_value' => 'Configure CDN for media files'
            ];
        }

        if (!$staticUrl || $staticUrl === '{{unsecure_base_url}}static/') {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'Frontend',
                'issue' => 'CDN not configured for static files',
                'details' => 'Using a CDN for static files improves page load times globally.',
                'current_value' => 'Local static file serving',
                'recommended_value' => 'Configure CDN for static files'
            ];
        }

        return $issues;
    }
}