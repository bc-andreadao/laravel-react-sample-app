export const ApiService = {

    _extractPaginationData(response) {
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
    },

    _extractRateLimitData(response) {
        return {
            resetMs: parseInt(response.headers['x-rate-limit-time-reset-ms']),
            windowMs: parseInt(response.headers['x-rate-limit-time-window-ms']),
            requestsLeft: parseInt(response.headers['x-rate-limit-requests-left']),
            requestsQuota: parseInt(response.headers['x-rate-limit-requests-quota']),
        };
    },

    async getOrders(params = {}) {
        return this.fetchAllPages('v2/orders', params);
    },

    updateOrder(orderId, data) {
        return axios({
            method: 'put',
            url: `/bc-api/v2/orders/${orderId}`,
            data,
        });
    },

    deleteOrder(orderId) {
        return axios({
            method: 'delete',
            url: `/bc-api/v2/orders/${orderId}`,
        });
    },

    getResourceCollection(resource, params) {
        params = Object.assign({
            page: 1,
            limit: 10,
        }, params);

        return axios({
                method: 'get',
                url: `/bc-api/${resource}`,
                params,
            });
    },

    getResourceEntry(resource, params) {
        return axios({
            method: 'get',
            url: `/bc-api/${resource}`,
            params,
        });
    },

    updateResourceEntry(resource, data) {
        return axios({
            method: 'put',
            url: `/bc-api/${resource}`,
            data,
        });
    },

    deleteResourceEntry(resource, data) {
        return axios({
            method: 'delete',
            url: `/bc-api/${resource}`,
        });
    },

    async fetchAllPages(resource, params = {}) {
        // Initialize with default params
        params = Object.assign({
            page: 1,
            limit: 250, // BigCommerce max limit
        }, params);

        let allData = [];
        let hasNextPage = true;

        while (hasNextPage) {
            try {
                const response = await axios({
                    method: 'get',
                    url: `/bc-api/${resource}`,
                    params,
                });

                // Add the current page's data to our collection
                if (response?.data) {
                    allData = allData.concat(response?.data);
                }

                // // Check if there's a next page
                const pagination = this._extractPaginationData(response);
                hasNextPage = pagination.currentPage < pagination.totalPages;
                
                // Update page number for next iteration
                if (hasNextPage) {
                    params.page++;
                }

                // Optional: Add delay to respect rate limits
                const rateLimit = this._extractRateLimitData(response);
                if (rateLimit.requestsLeft <= 1) {
                    await new Promise(resolve => setTimeout(resolve, rateLimit.resetMs));
                }

        } catch (error) {
            if (error.response?.status === 429) {
                const rateLimit = this._extractRateLimitData(error.response);
                throw {
                    ...error,
                    rateLimit,
                    retryAfter: rateLimit.resetMs,
                };
            }

            throw error;
        }

            return allData;
        }
    }
};