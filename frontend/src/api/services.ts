import { getData, postData } from './client'

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
  checkout: (payload: object) => postData<any>('/user/order/checkout', payload),
  checkPayment: (tradeNo: string) => getData<any>('/user/order/check', { trade_no: tradeNo }),
  coupon: (payload: object) => postData<any>('/user/coupon/check', payload),
}

export const serviceApi = {
  notices: () => getData<any[]>('/user/notice/fetch'),
  tickets: (params?: object) => getData<any>('/user/ticket/fetch', params),
  createTicket: (payload: object) => postData<any>('/user/ticket/save', payload),
  replyTicket: (payload: object) => postData<any>('/user/ticket/reply', payload),
  closeTicket: (id: number) => postData<any>('/user/ticket/close', { id }),
  servers: () => getData<any[]>('/user/server/fetch'),
  knowledge: () => getData<any[]>('/user/knowledge/fetch'),
  knowledgeCategories: () => getData<any[]>('/user/knowledge/getCategory'),
  traffic: () => getData<any>('/user/stat/getTrafficLog'),
  invites: () => getData<any>('/user/invite/fetch'),
  inviteDetails: () => getData<any>('/user/invite/details'),
  createInvite: () => getData<any>('/user/invite/save'),
  giftHistory: () => getData<any>('/user/gift-card/history'),
  giftCheck: (code: string) => postData<any>('/user/gift-card/check', { code }),
  redeemGift: (code: string) => postData<any>('/user/gift-card/redeem', { code }),
}
