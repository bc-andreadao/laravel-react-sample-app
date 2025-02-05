export const extractPaginationData = (response) => {
    return {
        total: parseInt(response.data?.meta?.pagination?.total || 0),
        totalPages: parseInt(response.data?.meta?.pagination?.total_pages || 0),
        currentPage: parseInt(response.data?.meta?.pagination?.current_page || 1),
        perPage: parseInt(response.data?.meta?.pagination?.per_page || 10),
        count: parseInt(response.data?.meta?.pagination?.count || 0),
        previousLink: response.data?.meta?.pagination?.links?.previous || null,
        currentLink: response.data?.meta?.pagination?.links?.current || null,
        nextLink: response.data?.meta?.pagination?.links?.next || null,
    };
};

export const extractRateLimitData = (response) => {
    return {
        resetMs: parseInt(response.headers['x-rate-limit-time-reset-ms']),
        windowMs: parseInt(response.headers['x-rate-limit-time-window-ms']),
        requestsLeft: parseInt(response.headers['x-rate-limit-requests-left']),
        requestsQuota: parseInt(response.headers['x-rate-limit-requests-quota']),
    };
}; 