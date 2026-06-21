<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px">
        <el-button @click="router.back()" :icon="ArrowLeft" circle />
        <h2 class="page-title">承运商详情</h2>
      </div>
      <el-button type="primary" @click="handleEdit">编辑</el-button>
    </div>

    <div v-loading="loading">
      <el-row :gutter="16" v-if="detail">
        <el-col :span="16">
          <div class="card-wrapper" style="margin-bottom:16px">
            <h3 style="margin:0 0 16px;font-size:15px">基本信息</h3>
            <el-descriptions :column="3" border>
              <el-descriptions-item label="编码">{{ detail.carrier_code }}</el-descriptions-item>
              <el-descriptions-item label="名称">{{ detail.carrier_name }}</el-descriptions-item>
              <el-descriptions-item label="类型">
                {{ getMetaLabel(meta.carrier_types, detail.carrier_type) }}
              </el-descriptions-item>
              <el-descriptions-item label="状态">
                <el-tag :type="statusTagType(detail.status)">{{ getMetaLabel(meta.carrier_statuses, detail.status) }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item label="优先级">{{ detail.priority }}</el-descriptions-item>
              <el-descriptions-item label="国家">{{ detail.country }}</el-descriptions-item>
              <el-descriptions-item label="联系人">{{ detail.contact_name }}</el-descriptions-item>
              <el-descriptions-item label="联系电话">{{ detail.contact_phone }}</el-descriptions-item>
              <el-descriptions-item label="邮箱">{{ detail.contact_email }}</el-descriptions-item>
              <el-descriptions-item label="备注" :span="3">{{ detail.remark || '-' }}</el-descriptions-item>
            </el-descriptions>
          </div>

          <div class="card-wrapper" style="margin-bottom:16px">
            <h3 style="margin:0 0 16px;font-size:15px">接入配置</h3>
            <el-descriptions :column="2" border>
              <el-descriptions-item label="协议类型">{{ detail.protocol_type?.toUpperCase() || '-' }}</el-descriptions-item>
              <el-descriptions-item label="认证方式">{{ detail.auth_type || '-' }}</el-descriptions-item>
              <el-descriptions-item label="API地址" :span="2">{{ detail.api_base_url || '-' }}</el-descriptions-item>
              <el-descriptions-item label="回调地址" :span="2">{{ detail.callback_url || '-' }}</el-descriptions-item>
              <el-descriptions-item label="超时(秒)">{{ detail.timeout_seconds }}</el-descriptions-item>
              <el-descriptions-item label="重试次数">{{ detail.retry_times }}</el-descriptions-item>
              <el-descriptions-item label="限频/分">{{ detail.rate_limit }}</el-descriptions-item>
              <el-descriptions-item label="配置状态">
                <el-tag :type="detail.config_status === 1 ? 'success' : 'info'">
                  {{ detail.config_status === 1 ? '已启用' : '已禁用' }}
                </el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </div>

          <div class="card-wrapper">
            <h3 style="margin:0 0 16px;font-size:15px">服务产品</h3>
            <el-table :data="detail.products || []" border stripe size="small">
              <el-table-column prop="product_code" label="产品编码" width="130" />
              <el-table-column prop="product_name" label="产品名称" min-width="140" />
              <el-table-column prop="service_type" label="服务类型" width="90">
                <template #default="{ row }">{{ getMetaLabel(meta.service_types, row.service_type) }}</template>
              </el-table-column>
              <el-table-column label="配送时效" width="120">
                <template #default="{ row }">
                  {{ row.delivery_days_min }}-{{ row.delivery_days_max }}天
                </template>
              </el-table-column>
              <el-table-column prop="weight_limit_kg" label="限重(kg)" width="100" />
              <el-table-column prop="status" label="状态" width="80" align="center">
                <template #default="{ row }">
                  <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{ row.status === 1 ? '启用' : '禁用' }}
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-col>

        <el-col :span="8">
          <div class="card-wrapper" style="margin-bottom:16px">
            <h3 style="margin:0 0 16px;font-size:15px">健康检查</h3>
            <el-button type="primary" size="small" @click="doHealthCheck" :loading="healthLoading">
              执行检查
            </el-button>
            <div v-if="healthResult" style="margin-top:12px">
              <el-result
                :icon="healthResult.healthy ? 'success' : 'error'"
                :title="healthResult.healthy ? '服务正常' : '服务异常'"
                :sub-title="healthResult.error_message || '延迟: ' + healthResult.latency_ms + 'ms'"
              />
            </div>
          </div>
        </el-col>
      </el-row>

      <el-empty v-else description="承运商不存在" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft } from '@element-plus/icons-vue'
import request from '@/utils/request'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const healthLoading = ref(false)
const healthResult = ref(null)
const detail = ref(null)

const meta = reactive({
  carrier_types: [],
  carrier_statuses: [],
  service_types: [],
})

const getMetaLabel = (arr, val) => {
  const item = arr.find(i => i.value === val)
  return item ? item.label : String(val)
}

const statusTagType = (status) => {
  const map = { 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }
  return map[status] || 'info'
}

const loadDetail = async () => {
  loading.value = true
  try {
    detail.value = await request.get(`/carriers/${route.params.id}`)
  } catch (e) { /* ignore */ }
  loading.value = false
}

const doHealthCheck = async () => {
  healthLoading.value = true
  try {
    healthResult.value = await request.get(`/carriers/health/${route.params.id}`)
  } catch (e) {
    ElMessage.error('健康检查失败')
  }
  healthLoading.value = false
}

const handleEdit = () => {
  router.push('/carriers')
}

onMounted(async () => {
  try {
    const res = await request.get('/meta/all')
    Object.assign(meta, res)
  } catch (e) { /* ignore */ }
  loadDetail()
})
</script>
