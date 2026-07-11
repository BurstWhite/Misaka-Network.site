import axios, { AxiosError } from 'axios'
import { runtimeConfig } from '@/app/config'
import { clearToken, readToken } from '@/stores/auth-token'

export interface ApiEnvelope<T> { status?: string; message?: string; data: T; error?: unknown }

export class ApiError extends Error {
  constructor(message: string, public status?: number, public details?: unknown) { super(message) }
}

export const api = axios.create({ baseURL: runtimeConfig.apiBase, timeout: 15_000 })

if (runtimeConfig.features.demo) {
  api.defaults.adapter = async (config) => {
    const url = String(config.url || '')
    const now = Math.floor(Date.now() / 1000)
    const orders = [
      { trade_no: '20260710001', plan_name: '标准套餐', period: '月付', total_amount: 6800, status: 3, created_at: now - 3600 },
      { trade_no: '20260610009', plan_name: '标准套餐', period: '月付', total_amount: 6800, status: 3, created_at: now - 86400 * 30 },
      { trade_no: '20260510016', plan_name: '标准套餐', period: '月付', total_amount: 6800, status: 3, created_at: now - 86400 * 61 },
    ]
    let data: any = []
    if (url.includes('/user/info')) data = { email: 'user@example.com', plan: { name: '标准套餐' }, transfer_enable: 536870912000, expired_at: now + 86400 * 32, next_reset_at: now + 86400 * 14 }
    else if (url.includes('/user/getSubscribe')) data = { u: 45197156352, d: 218613268480, transfer_enable: 536870912000, subscribe_url: 'https://example.com/s/demo' }
    else if (url.includes('/notice/fetch')) data = [
      { id: 1, title: '关于系统维护的通知', created_at: now - 7200 }, { id: 2, title: '五一劳动节服务调整说明', created_at: now - 86400 * 3 },
      { id: 3, title: '新加坡节点升级完成', created_at: now - 86400 * 7 }, { id: 4, title: '推荐奖励计划更新', created_at: now - 86400 * 12 },
    ]
    else if (url.includes('/order/fetch')) data = orders
    else if (url.includes('/plan/fetch')) data = [{ id: 1, name: '标准套餐', month_price: 6800, quarter_price: 18800, year_price: 64800, transfer_enable: 536870912000 }]
    return { data: { status: 'success', data }, status: 200, statusText: 'OK', headers: {}, config }
  }
}

api.interceptors.request.use((request) => {
  const token = readToken()
  if (token) request.headers.Authorization = token.startsWith('Bearer ') ? token : `Bearer ${token}`
  request.headers['Content-Language'] = localStorage.getItem('misaka.locale') || 'zh-CN'
  return request
})

api.interceptors.response.use(
  (response) => {
    const body = response.data as ApiEnvelope<unknown>
    if (body?.status === 'fail') throw new ApiError(body.message || '请求失败', response.status, body.error)
    return response
  },
  (error: AxiosError<any>) => {
    if (error.response?.status === 401) clearToken()
    const message = error.response?.data?.message || error.message || '网络请求失败'
    return Promise.reject(new ApiError(message, error.response?.status, error.response?.data))
  },
)

export async function getData<T>(url: string, params?: object, signal?: AbortSignal): Promise<T> {
  const response = await api.get<ApiEnvelope<T>>(url, { params, signal })
  return response.data.data
}

export async function postData<T>(url: string, data?: object, signal?: AbortSignal): Promise<T> {
  const response = await api.post<ApiEnvelope<T>>(url, data, { signal })
  return response.data.data
}
