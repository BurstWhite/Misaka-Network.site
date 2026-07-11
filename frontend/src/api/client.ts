import axios, { AxiosError } from 'axios'
import { runtimeConfig } from '@/app/config'
import { clearToken, readToken } from '@/stores/auth-token'

export interface ApiEnvelope<T> { status?: string; message?: string; data?: T; error?: unknown }

export class ApiError extends Error {
  constructor(message: string, public status?: number, public details?: unknown) { super(message) }
}

export const api = axios.create({ baseURL: runtimeConfig.apiBase, timeout: 15_000 })

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

function responseData<T>(payload: ApiEnvelope<T> | { data?: T } | T): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data?: T }).data as T
  }
  return payload as T
}

export async function getData<T>(url: string, params?: object, signal?: AbortSignal): Promise<T> {
  const response = await api.get<ApiEnvelope<T>>(url, { params, signal })
  return responseData(response.data)
}

export async function postData<T>(url: string, data?: object, signal?: AbortSignal): Promise<T> {
  const response = await api.post<ApiEnvelope<T>>(url, data, { signal })
  return responseData(response.data)
}
