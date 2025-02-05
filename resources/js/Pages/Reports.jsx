// For batch operations
import { BatchApiService } from '@/services';

export default class Reports extends React.Component {
    async exportAllOrders() {
        const response = await BatchApiService.exportOrders();
        // Process all orders...
    }
} 