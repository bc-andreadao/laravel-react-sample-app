import Navigation from '@/Components/Navigation';
import Spinner from '@/Components/Spinner';
import Table from '@/Components/Table';
import { BatchApiService } from '@/services';

import { Head } from '@inertiajs/react';
import React from 'react';

export default class Reports extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
            isOrdersLoading: true,
            orders: {
                data: [],
            },
            tableHeaders: [
                {
                    label: "Order ID",
                    index: "id",
                    callback: function (orderId) {
                        return orderId;
                    },
                },
                {
                    label: "Billing Name",
                    index: "billing_address",
                    callback: function (billingAddress) {
                        return `${billingAddress.first_name} ${billingAddress.last_name}`;
                    },
                },
                {
                    label: "Total",
                    index: "total_inc_tax",
                    callback: function (total) {
                        return new Intl.NumberFormat('en-US', { 
                            style: 'currency', 
                            currency: 'USD' 
                        }).format(total);
                    },
                },
            ]
        };
    }

    componentDidMount() {
        this.loadAllOrders();
    }

    loadAllOrders() {
        BatchApiService.exportOrders()
            .then(this.handleOrdersResponse.bind(this))
            .catch(error => {
                if (error.response?.status === 429) {
                    setTimeout(() => this.loadAllOrders(), error.retryAfter);
                }
                this.setState({ isOrdersLoading: false });
            });
    }

    handleOrdersResponse(response) {
        this.setState({
            isOrdersLoading: false,
            orders: {
                data: response.data,
            }
        });
    }

    hasOrders() {
        return (this.state.orders.data.length > 0);
    }

    render() {
        return (
            <>
                <Head title="Orders Report" />
                <Navigation />
                <div className="container mx-auto p-5">
                    <div className="content col-span-3 grid-col-3 rounded bg-gray-100 shadow-lg p-4">
                        <h2 className="text-xl font-bold mb-6">Complete Orders Report</h2>
                        {
                            this.state.isOrdersLoading
                                ?
                                <Spinner />
                                :
                                this.hasOrders()
                                    ?
                                    <section>
                                        <div className="mb-4 text-sm text-gray-600">
                                            Showing all {this.state.orders.data.length} orders
                                        </div>
                                        <Table 
                                            tableHeaders={this.state.tableHeaders} 
                                            tableData={this.state.orders.data} 
                                        />
                                    </section>
                                    :
                                    <section>
                                        <div className="emptyTable">No orders exist yet!</div>
                                    </section>
                        }
                    </div>
                </div>
            </>
        );
    }
} 