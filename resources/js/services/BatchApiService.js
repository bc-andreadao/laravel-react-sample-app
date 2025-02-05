import { extractPaginationData, extractRateLimitData } from './utils/responseExtractors';

export const BatchApiService = {
        _extractPaginationData: extractPaginationData,
        _extractRateLimitData: extractRateLimitData,
    

    async fetchAllPages(resource, params = {}) {
        params = Object.assign({
            page: 1,
            limit: 250,
        }, params);

        let allData = [];
        let hasNextPage = true;
        let lastResponse = null;

        while (hasNextPage) {
            try {
                const response = await axios({
                    method: 'get',
                    url: `/bc-api/${resource}`,
                    params,
                });
                
                lastResponse = response;

                if (response?.data) {
                    allData = allData.concat(response?.data);
                }

                const pagination = this._extractPaginationData(response);
                hasNextPage = pagination.currentPage < pagination.totalPages;
                
                if (hasNextPage) {
                    params.page++;
                }

                const rateLimit = this._extractRateLimitData(response);
                if (rateLimit.requestsLeft <= 1) {
                    await new Promise(resolve => setTimeout(resolve, rateLimit.resetMs));
                }

            } catch (error) {
                if (error.response?.status === 429) {
                    const rateLimit = this._extractRateLimitData(error.response);
                    throw {
                        ...error,
                        retryAfter: rateLimit.resetMs
                    };
                }
                throw error;
            }
        }

        lastResponse.data = allData;
        return lastResponse;
    },

    // Add specific batch operation methods
    async exportOrders(params = {}) {
        return this.fetchAllPages('v2/orders', params);
    },

    async generateOrderReport(dateRange) {
        const response = await this.fetchAllPages('v2/orders', {
            params: {
                date_created: dateRange
            }
        });

        return {
            data: response.data,
            stats: this._calculateOrderStats(response.data)
        };
    },

    _calculateOrderStats(orders) {
        return {
            totalOrders: orders.length,
            totalRevenue: orders.reduce((sum, order) => sum + order.total_inc_tax, 0),
            // ... other calculations
        };
    }
}; 