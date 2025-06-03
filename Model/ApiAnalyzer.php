<?php

namespace Performance\Review\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Integration\Model\ResourceModel\Oauth\Token\CollectionFactory as TokenCollectionFactory;

class ApiAnalyzer
{
    private ScopeConfigInterface $scopeConfig;
    private ResourceConnection $resourceConnection;
    private TokenCollectionFactory $tokenCollectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        TokenCollectionFactory $tokenCollectionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->tokenCollectionFactory = $tokenCollectionFactory;
    }

    public function analyzeApi(): array
    {
        $issues = [];

        // Check API rate limiting
        $rateLimitIssues = $this->checkApiRateLimiting();
        if (!empty($rateLimitIssues)) {
            $issues = array_merge($issues, $rateLimitIssues);
        }

        // Check API authentication
        $authIssues = $this->checkApiAuthentication();
        if (!empty($authIssues)) {
            $issues = array_merge($issues, $authIssues);
        }

        // Check GraphQL configuration
        $graphqlIssues = $this->checkGraphQLConfiguration();
        if (!empty($graphqlIssues)) {
            $issues = array_merge($issues, $graphqlIssues);
        }

        // Check REST API configuration
        $restIssues = $this->checkRestApiConfiguration();
        if (!empty($restIssues)) {
            $issues = array_merge($issues, $restIssues);
        }

        // Check API response caching
        $cacheIssues = $this->checkApiCaching();
        if (!empty($cacheIssues)) {
            $issues = array_merge($issues, $cacheIssues);
        }

        return $issues;
    }

    private function checkApiRateLimiting(): array
    {
        $issues = [];

        // Check if rate limiting is configured
        $guestLimit = $this->scopeConfig->getValue(
            'webapi/webapisecurity/rate_limit_guest',
            ScopeInterface::SCOPE_STORE
        );
        
        $customerLimit = $this->scopeConfig->getValue(
            'webapi/webapisecurity/rate_limit_customer',
            ScopeInterface::SCOPE_STORE
        );

        if (!$guestLimit && !$customerLimit) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'API',
                'issue' => 'API rate limiting not configured',
                'details' => 'Without rate limiting, your API is vulnerable to abuse and DoS attacks.',
                'current_value' => 'No rate limiting',
                'recommended_value' => 'Configure rate limits in Stores > Configuration > Services > Magento Web API'
            ];
        } elseif ($guestLimit > 1000 || $customerLimit > 1000) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'API rate limits may be too high',
                'details' => sprintf(
                    'Current limits: Guest: %s, Customer: %s. High limits may not effectively prevent abuse.',
                    $guestLimit ?: 'unlimited',
                    $customerLimit ?: 'unlimited'
                ),
                'current_value' => sprintf('Guest: %s, Customer: %s', $guestLimit, $customerLimit),
                'recommended_value' => 'Guest: 100-200, Customer: 500-1000 requests per hour'
            ];
        }

        return $issues;
    }

    private function checkApiAuthentication(): array
    {
        $issues = [];

        try {
            // Check for excessive OAuth tokens
            $tokenCollection = $this->tokenCollectionFactory->create();
            $totalTokens = $tokenCollection->getSize();
            
            if ($totalTokens > 10000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'API',
                    'issue' => 'Excessive OAuth tokens',
                    'details' => sprintf(
                        'Found %s OAuth tokens. Old tokens should be cleaned up regularly.',
                        number_format($totalTokens)
                    ),
                    'current_value' => number_format($totalTokens) . ' tokens',
                    'recommended_value' => 'Implement token cleanup policy'
                ];
            }

            // Check for expired tokens
            $connection = $this->resourceConnection->getConnection();
            $expiredTokens = $connection->fetchOne(
                "SELECT COUNT(*) FROM oauth_token WHERE expires IS NOT NULL AND expires < NOW()"
            );

            if ($expiredTokens > 1000) {
                $issues[] = [
                    'priority' => 'Medium',
                    'category' => 'API',
                    'issue' => 'Many expired OAuth tokens',
                    'details' => sprintf(
                        'Found %s expired tokens that should be cleaned up.',
                        number_format($expiredTokens)
                    ),
                    'current_value' => number_format($expiredTokens) . ' expired tokens',
                    'recommended_value' => 'Clean up expired tokens regularly'
                ];
            }
        } catch (\Exception $e) {
            // Skip if we can't check tokens
        }

        return $issues;
    }

    private function checkGraphQLConfiguration(): array
    {
        $issues = [];

        // Check GraphQL query depth limit
        $queryDepth = $this->scopeConfig->getValue(
            'graphql/validation/maximum_query_depth',
            ScopeInterface::SCOPE_STORE
        );

        if (!$queryDepth || $queryDepth > 20) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'GraphQL query depth limit not configured',
                'details' => 'Deep GraphQL queries can cause performance issues and potential DoS.',
                'current_value' => $queryDepth ?: 'No limit',
                'recommended_value' => '10-15 levels maximum'
            ];
        }

        // Check query complexity
        $queryComplexity = $this->scopeConfig->getValue(
            'graphql/validation/maximum_query_complexity',
            ScopeInterface::SCOPE_STORE
        );

        if (!$queryComplexity || $queryComplexity > 1000) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'GraphQL query complexity limit not configured',
                'details' => 'Complex queries can overwhelm the server and cause timeouts.',
                'current_value' => $queryComplexity ?: 'No limit',
                'recommended_value' => '300-500 complexity points'
            ];
        }

        // Check introspection in production
        $introspectionEnabled = $this->scopeConfig->getValue(
            'graphql/validation/disable_introspection',
            ScopeInterface::SCOPE_STORE
        );

        if (!$introspectionEnabled) {
            $issues[] = [
                'priority' => 'Low',
                'category' => 'API',
                'issue' => 'GraphQL introspection enabled',
                'details' => 'Introspection should be disabled in production for security.',
                'current_value' => 'Enabled',
                'recommended_value' => 'Disable introspection in production'
            ];
        }

        return $issues;
    }

    private function checkRestApiConfiguration(): array
    {
        $issues = [];

        // Check API response fields filtering
        $defaultPageSize = $this->scopeConfig->getValue(
            'webapi/soap/default_page_size',
            ScopeInterface::SCOPE_STORE
        );

        if (!$defaultPageSize || $defaultPageSize > 100) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'API default page size too large',
                'details' => 'Large page sizes can cause memory issues and slow responses.',
                'current_value' => $defaultPageSize ?: 'No limit',
                'recommended_value' => '20-50 items per page'
            ];
        }

        // Check maximum page size
        $maxPageSize = $this->scopeConfig->getValue(
            'webapi/soap/max_page_size',
            ScopeInterface::SCOPE_STORE
        );

        if (!$maxPageSize || $maxPageSize > 500) {
            $issues[] = [
                'priority' => 'High',
                'category' => 'API',
                'issue' => 'API maximum page size not limited',
                'details' => 'Without limits, clients can request huge result sets causing performance issues.',
                'current_value' => $maxPageSize ?: 'No limit',
                'recommended_value' => '100-200 items maximum'
            ];
        }

        return $issues;
    }

    private function checkApiCaching(): array
    {
        $issues = [];

        // Check if API responses are being cached
        $apiCacheEnabled = $this->scopeConfig->getValue(
            'webapi/soap/api_cache_enabled',
            ScopeInterface::SCOPE_STORE
        );

        if (!$apiCacheEnabled) {
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'API response caching not enabled',
                'details' => 'Caching API responses can significantly improve performance for read operations.',
                'current_value' => 'Disabled',
                'recommended_value' => 'Enable API caching for GET requests'
            ];
        }

        // Check Varnish for API endpoints
        $varnishEnabled = $this->scopeConfig->getValue(
            'system/full_page_cache/caching_application',
            ScopeInterface::SCOPE_STORE
        );

        if ($varnishEnabled != '2') { // 2 = Varnish
            $issues[] = [
                'priority' => 'Medium',
                'category' => 'API',
                'issue' => 'API endpoints not cached by Varnish',
                'details' => 'Varnish can cache API responses for better performance.',
                'current_value' => 'Not using Varnish',
                'recommended_value' => 'Configure Varnish to cache API endpoints'
            ];
        }

        return $issues;
    }
}