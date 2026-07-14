import { getData, postData, postRawData } from './client'

export const guestApi = {
  plans: () => getData<any[]>('/guest/plan/fetch'),
  servers: () => getData<any[]>('/guest/server/fetch'),
}

export const authApi = {
  config: () => getData<any>('/guest/comm/config'),
  login: (payload: object) => postData<any>('/passport/auth/login', payload),
  register: (payload: object) => postData<any>('/passport/auth/register', payload),
  forget: (payload: object) => postData<any>('/passport/auth/forget', payload),
  sendCode: (payload: object) => postData<any>('/passport/comm/sendEmailVerify', payload),
}

export const userApi = {
  info: () => getData<any>('/user/info'),
  subscribe: () => getData<any>('/user/getSubscribe'),
  resetSecurity: () => getData<string>('/user/resetSecurity'),
  stats: () => getData<any>('/user/getStat'),
  config: () => getData<any>('/user/comm/config'),
  update: (payload: object) => postData<any>('/user/update', payload),
  changePassword: (payload: object) => postData<any>('/user/changePassword', payload),
  sessions: () => getData<any[]>('/user/getActiveSession'),
  removeSession: (payload: object) => postData<any>('/user/removeActiveSession', payload),
}

export const commerceApi = {
  plans: () => getData<any[]>('/user/plan/fetch'),
  orders: () => getData<any>('/user/order/fetch'),
  order: (tradeNo: string) => getData<any>('/user/order/detail', { trade_no: tradeNo }),
  createOrder: (payload: object) => postData<any>('/user/order/save', payload),
  cancelOrder: (tradeNo: string) => postData<any>('/user/order/cancel', { trade_no: tradeNo }),
  manualSubmit: (tradeNo: string) => postData<any>('/user/order/manual-submit', { trade_no: tradeNo }),
  paymentMethods: () => getData<any[]>('/user/order/getPaymentMethod'),
  checkout: (payload: object) => postRawData<any>('/user/order/checkout', payload),
  checkPayment: (tradeNo: string) => getData<any>('/user/order/check', { trade_no: tradeNo }),
  coupon: (payload: object) => postData<any>('/user/coupon/check', payload),
  savedCoupons: () => getData<any[]>('/user/coupon/saved'),
  saveCoupon: (code: string) => postData<any>('/user/coupon/save', { code }),
  removeSavedCoupon: (couponId: string | number) => postData<boolean>('/user/coupon/remove', { coupon_id: couponId }),
  bestCoupon: (payload: { plan_id: string | number, period: string }) => postData<any>('/user/coupon/best', payload),
}

export const serviceApi = {
  notices: () => getData<any[]>('/user/notice/fetch'),
  tickets: (params?: object) => getData<any>('/user/ticket/fetch', params),
  createTicket: (payload: object) => postData<any>('/user/ticket/save', payload),
  replyTicket: (payload: object) => postData<any>('/user/ticket/reply', payload),
  closeTicket: (id: number) => postData<any>('/user/ticket/close', { id }),
  servers: () => getData<any[]>('/user/server/fetch'),
  knowledge: (params?: { keyword?: string }) => getData<any[]>('/user/knowledge/fetch', { language: localStorage.getItem('misaka.locale') || 'zh-CN', ...params }),
  knowledgeCategories: () => getData<any[]>('/user/knowledge/getCategory', { language: localStorage.getItem('misaka.locale') || 'zh-CN' }),
  traffic: (params?: { days?: number }) => getData<any>('/user/stat/getTrafficLog', params),
  invites: () => getData<any>('/user/invite/fetch'),
  inviteDetails: (params?: object) => getData<any>('/user/invite/details', params),
  createInvite: () => getData<any>('/user/invite/save'),
}
