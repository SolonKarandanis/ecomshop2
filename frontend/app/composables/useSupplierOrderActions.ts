import type { Order } from '~/types/order'
import type { SupplierAction } from '~/utils/orderStatus'

/**
 * Runs a Supplier's ship / deliver / cancel action on an Order. The status
 * changes on screen straight away, and goes back if the API refuses.
 */
  export function useSupplierOrderActions() {
  const toast = useToast()
  const pending = ref(false)

  async function run(order: Pick<Order, 'id' | 'order_status' | 'payment_status'>, action: SupplierAction): Promise<boolean> {
    const previous = order.order_status
    order.order_status = SUPPLIER_ACTION_TARGET[action]
    pending.value = true

    try {
      const response = await useApi()<{ data: Order }>(`/supplier-orders/${order.id}/${action}`, { method: 'POST' })
      order.order_status = response.data.order_status
      order.payment_status = response.data.payment_status
      return true
    }
    catch (error) {
      order.order_status = previous
      toast.add({ title: apiErrorMessage(error, 'The order couldn\'t be updated. Please try again.'), color: 'error' })
      return false
    }
    finally {
      pending.value = false
    }
  }

  return { pending, run }
}
