import axios from 'axios'
import { ElMessage } from 'element-plus'

const request = axios.create({
  baseURL: '/api',
  timeout: 30000,
})

request.interceptors.request.use(
  (config) => {
    if (config.method === 'get' && !config.allowCache) {
      const separator = config.url.includes('?') ? '&' : '?'
      config.url = `${config.url}${separator}_t=${Date.now()}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

request.interceptors.response.use(
  (response) => {
    const res = response.data
    if (res.success === false) {
      if (!response.config.skipErrorNotification) {
        ElMessage.error(res.message || '请求失败')
      }
      return Promise.reject(res)
    }
    return res.data !== undefined ? res.data : res
  },
  (error) => {
    if (!error.config?.skipErrorNotification) {
      ElMessage.error(error.message || '网络错误')
    }
    return Promise.reject(error)
  }
)

export default request
