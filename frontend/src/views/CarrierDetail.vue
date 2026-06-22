<template>
  <div>
    <div class="page-header">
      <div style="display:flex;align-items:center;gap:12px">
        <el-button @click="router.back()" :icon="ArrowLeft" circle />
        <h2 class="page-title">承运商详情</h2>
      </div>
      <div style="display:flex;gap:8px">
        <el-button type="primary" @click="handleEdit">编辑</el-button>
      </div>
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 style="margin:0;font-size:15px">健康检查</h3>
              <el-button type="primary" size="small" @click="doHealthCheck" :loading="healthLoading">
                执行检查
              </el-button>
            </div>
            <div v-if="healthResult">
              <el-result
                :icon="healthResult.healthy ? 'success' : 'error'"
                :title="healthResult.healthy ? '服务正常' : '服务异常'"
                :sub-title="healthResult.error_message || '延迟: ' + healthResult.latency_ms + 'ms'"
              />
            </div>
          </div>

          <div class="card-wrapper" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 style="margin:0;font-size:15px">联动校验</h3>
              <el-button type="warning" size="small" @click="doLinkageCheck" :loading="linkageLoading">
                执行校验
              </el-button>
            </div>
            <div v-if="linkageResult">
              <el-result
                :icon="linkageResult.all_passed ? 'success' : 'warning'"
                :title="linkageResult.all_passed ? '校验通过' : '校验未通过'"
                :sub-title="`${linkageResult.passed_count}/${linkageResult.total_count} 项通过`"
              />
              <div style="margin-top:8px">
                <div v-for="check in linkageResult.checks" :key="check.name" style="margin-bottom:8px">
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                    <el-icon :color="check.passed ? '#67c23a' : '#f56c6c'">
                      <CircleCheckFilled v-if="check.passed" />
                      <CircleCloseFilled v-else />
                    </el-icon>
                    <span :style="{fontWeight:500,color:check.passed?'#67c23a':'#f56c6c'}">{{ check.name }}</span>
                  </div>
                  <div v-if="check.errors && check.errors.length" style="padding-left:24px">
                    <div v-for="err in check.errors" :key="err" style="color:#f56c6c;font-size:12px">
                      ✗ {{ err }}
                    </div>
                  </div>
                  <div v-if="check.warnings && check.warnings.length" style="padding-left:24px">
                    <div v-for="warn in check.warnings" :key="warn" style="color:#e6a23c;font-size:12px">
                      ⚠ {{ warn }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card-wrapper">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 style="margin:0;font-size:15px">配置历史</h3>
              <el-button size="small" @click="loadConfigHistory">刷新</el-button>
            </div>
            <el-table :data="configHistory" border stripe size="small" max-height="300">
              <el-table-column prop="change_type" label="类型" width="70">
                <template #default="{ row }">
                  <el-tag :type="changeTypeTag(row.change_type)" size="small">
                    {{ changeTypeLabel(row.change_type) }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="change_remark" label="说明" min-width="100" show-overflow-tooltip />
              <el-table-column prop="created_at" label="时间" width="140" />
              <el-table-column label="操作" width="70" fixed="right">
                <template #default="{ row }">
                  <el-button type="danger" size="small" link @click="handleRollbackConfig(row)">回滚</el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-col>
      </el-row>

      <el-empty v-else description="承运商不存在" />
    </div>

    <el-dialog v-model="rollbackDialogVisible" title="确认回滚配置" width="500px" destroy-on-close>
      <el-alert type="warning" :closable="false" style="margin-bottom:16px">
        回滚将把承运商配置恢复到所选历史版本，当前配置将被覆盖。回滚前会自动保存当前配置快照。
      </el-alert>
      <el-form :model="rollbackForm" label-width="80px">
        <el-form-item label="历史版本">
          <el-input :value="rollbackForm.history_remark" disabled />
        </el-form-item>
        <el-form-item label="操作人">
          <el-input v-model="rollbackForm.operator" placeholder="请输入操作人" />
        </el-form-item>
        <el-form-item label="回滚原因">
          <el-input v-model="rollbackForm.remark" type="textarea" :rows="2" placeholder="请输入回滚原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rollbackDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="rollbackLoading" @click="confirmRollbackConfig">确认回滚</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="trackingRollbackDialogVisible" title="轨迹数据回滚" width="600px" destroy-on-close>
      <el-tabs v-model="trackingRollbackTab">
        <el-tab-pane label="按时间范围" name="time">
          <el-form :model="trackingRollbackTimeForm" label-width="100px">
            <el-form-item label="承运商编码">
              <el-input v-model="trackingRollbackTimeForm.carrier_code" :disabled="!!detail" />
            </el-form-item>
            <el-form-item label="开始时间">
              <el-date-picker v-model="trackingRollbackTimeForm.start_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
            </el-form-item>
            <el-form-item label="结束时间">
              <el-date-picker v-model="trackingRollbackTimeForm.end_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" />
            </el-form-item>
            <el-form-item label="操作人">
              <el-input v-model="trackingRollbackTimeForm.operator" placeholder="请输入操作人" />
            </el-form-item>
            <el-form-item label="回滚原因">
              <el-input v-model="trackingRollbackTimeForm.remark" type="textarea" :rows="2" />
            </el-form-item>
          </el-form>
        </el-tab-pane>
        <el-tab-pane label="按运单号" name="tracking_no">
          <el-form :model="trackingRollbackNoForm" label-width="100px">
            <el-form-item label="运单号">
              <el-input v-model="trackingRollbackNoForm.tracking_no" placeholder="请输入运单号" />
            </el-form-item>
            <el-form-item label="承运商编码">
              <el-input v-model="trackingRollbackNoForm.carrier_code" :disabled="!!detail" placeholder="留空则回滚所有承运商" />
            </el-form-item>
            <el-form-item label="操作人">
              <el-input v-model="trackingRollbackNoForm.operator" placeholder="请输入操作人" />
            </el-form-item>
            <el-form-item label="回滚原因">
              <el-input v-model="trackingRollbackNoForm.remark" type="textarea" :rows="2" />
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
      <template #footer>
        <el-button @click="trackingRollbackDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="trackingRollbackLoading" @click="confirmTrackingRollback">确认回滚</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { ArrowLeft, CircleCheckFilled, CircleCloseFilled } from '@element-plus/icons-vue'
import request from '@/utils/request'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const healthLoading = ref(false)
const healthResult = ref(null)
const linkageLoading = ref(false)
const linkageResult = ref(null)
const detail = ref(null)
const configHistory = ref([])
const rollbackDialogVisible = ref(false)
const rollbackLoading = ref(false)
const rollbackForm = reactive({
  history_id: null,
  history_remark: '',
  operator: '',
  remark: '',
})
const trackingRollbackDialogVisible = ref(false)
const trackingRollbackLoading = ref(false)
const trackingRollbackTab = ref('time')
const trackingRollbackTimeForm = reactive({
  carrier_code: '',
  start_time: '',
  end_time: '',
  operator: '',
  remark: '',
})
const trackingRollbackNoForm = reactive({
  tracking_no: '',
  carrier_code: '',
  operator: '',
  remark: '',
})

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

const changeTypeLabel = (type) => {
  const map = { create: '创建', update: '更新', rollback: '回滚' }
  return map[type] || type
}

const changeTypeTag = (type) => {
  const map = { create: 'success', update: '', rollback: 'warning' }
  return map[type] || 'info'
}

const loadDetail = async () => {
  loading.value = true
  try {
    detail.value = await request.get(`/carriers/${route.params.id}`)
    if (detail.value) {
      trackingRollbackTimeForm.carrier_code = detail.value.carrier_code
      trackingRollbackNoForm.carrier_code = detail.value.carrier_code
    }
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

const doLinkageCheck = async () => {
  linkageLoading.value = true
  try {
    linkageResult.value = await request.get(`/carriers/linkage-check/${route.params.id}`)
  } catch (e) {
    ElMessage.error('联动校验失败')
  }
  linkageLoading.value = false
}

const loadConfigHistory = async () => {
  try {
    const res = await request.get(`/carriers/config-history/${route.params.id}`)
    configHistory.value = res.items || []
  } catch (e) { /* ignore */ }
}

const handleRollbackConfig = async (row) => {
  try {
    await ElMessageBox.confirm(
      `确定回滚到「${row.change_remark || changeTypeLabel(row.change_type)}」版本？当前配置将被覆盖。`,
      '确认配置回滚',
      { type: 'warning', confirmButtonText: '确认回滚', cancelButtonText: '取消' }
    )
    rollbackForm.history_id = row.id
    rollbackForm.history_remark = row.change_remark || changeTypeLabel(row.change_type)
    rollbackForm.operator = ''
    rollbackForm.remark = ''
    rollbackDialogVisible.value = true
  } catch (e) { /* cancel */ }
}

const confirmRollbackConfig = async () => {
  rollbackLoading.value = true
  try {
    await request.post(`/carriers/rollback-config/${rollbackForm.history_id}`, {
      operator: rollbackForm.operator,
      remark: rollbackForm.remark,
    })
    ElMessage.success('配置回滚成功')
    rollbackDialogVisible.value = false
    await loadDetail()
    await loadConfigHistory()
  } catch (e) {
    ElMessage.error('配置回滚失败')
  }
  rollbackLoading.value = false
}

const confirmTrackingRollback = async () => {
  try {
    await ElMessageBox.confirm('确定回滚轨迹数据？此操作不可撤销。', '确认轨迹回滚', {
      type: 'warning',
      confirmButtonText: '确认回滚',
      cancelButtonText: '取消',
    })
  } catch (e) { return }

  trackingRollbackLoading.value = true
  try {
    if (trackingRollbackTab.value === 'time') {
      const form = trackingRollbackTimeForm
      if (!form.carrier_code || !form.start_time || !form.end_time) {
        ElMessage.warning('请填写完整信息')
        trackingRollbackLoading.value = false
        return
      }
      const res = await request.post('/tracking/rollback-time', form)
      ElMessage.success(`轨迹回滚成功，共回滚 ${res.rollback_count || 0} 条记录`)
    } else {
      const form = trackingRollbackNoForm
      if (!form.tracking_no) {
        ElMessage.warning('请输入运单号')
        trackingRollbackLoading.value = false
        return
      }
      const res = await request.post('/tracking/rollback-no', form)
      ElMessage.success(`轨迹回滚成功，共回滚 ${res.rollback_count || 0} 条记录`)
    }
    trackingRollbackDialogVisible.value = false
  } catch (e) {
    ElMessage.error('轨迹回滚失败')
  }
  trackingRollbackLoading.value = false
}

const handleEdit = () => {
  router.push('/carriers')
}

onMounted(async () => {
  try {
    const res = await request.get('/meta/all')
    Object.assign(meta, res)
  } catch (e) { /* ignore */ }
  await loadDetail()
  loadConfigHistory()
})
</script>
