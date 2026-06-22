import axios from 'axios'
import { ElMessage } from 'element-plus'

const request = axios.create({
  baseURL: '/api',
  timeout: 30000,
})

request.interceptors.response.use(
  (response) => {
    const res = response.data
    if (res.success === false) {
      ElMessage.error(res.message || '请求失败')
      return Promise.reject(res)
    }
    return res.data !== undefined ? res.data : res
  },
  (error) => {
    ElMessage.error(error.message || '网络错误')
    return Promise.reject(error)
  }
)

export default request
