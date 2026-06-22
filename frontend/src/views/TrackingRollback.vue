<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">轨迹回滚</h2>
    </div>

    <div class="card-wrapper" style="margin-bottom:16px">
      <h3 style="margin:0 0 16px;font-size:15px">执行回滚</h3>
      <el-tabs v-model="rollbackTab">
        <el-tab-pane label="按时间范围" name="time">
          <el-form :model="timeForm" :rules="timeRules" ref="timeFormRef" label-width="100px" style="max-width:600px">
            <el-form-item label="承运商编码" prop="carrier_code">
              <el-select v-model="timeForm.carrier_code" filterable placeholder="请选择承运商" style="width:100%">
                <el-option v-for="c in carrierOptions" :key="c.code" :label="`${c.label} (${c.code})`" :value="c.code" />
              </el-select>
            </el-form-item>
            <el-form-item label="开始时间" prop="start_time">
              <el-date-picker v-model="timeForm.start_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
            </el-form-item>
            <el-form-item label="结束时间" prop="end_time">
              <el-date-picker v-model="timeForm.end_time" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
            </el-form-item>
            <el-form-item label="操作人" prop="operator">
              <el-input v-model="timeForm.operator" placeholder="请输入操作人" />
            </el-form-item>
            <el-form-item label="回滚原因" prop="remark">
              <el-input v-model="timeForm.remark" type="textarea" :rows="2" placeholder="请输入回滚原因" />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" :loading="rollbackLoading" @click="executeTimeRollback">执行回滚</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
        <el-tab-pane label="按运单号" name="tracking_no">
          <el-form :model="noForm" :rules="noRules" ref="noFormRef" label-width="100px" style="max-width:600px">
            <el-form-item label="运单号" prop="tracking_no">
              <el-input v-model="noForm.tracking_no" placeholder="请输入运单号" />
            </el-form-item>
            <el-form-item label="承运商编码" prop="carrier_code">
              <el-select v-model="noForm.carrier_code" filterable clearable placeholder="留空则回滚所有承运商" style="width:100%">
                <el-option v-for="c in carrierOptions" :key="c.code" :label="`${c.label} (${c.code})`" :value="c.code" />
              </el-select>
            </el-form-item>
            <el-form-item label="操作人" prop="operator">
              <el-input v-model="noForm.operator" placeholder="请输入操作人" />
            </el-form-item>
            <el-form-item label="回滚原因" prop="remark">
              <el-input v-model="noForm.remark" type="textarea" :rows="2" placeholder="请输入回滚原因" />
            </el-form-item>
            <el-form-item>
              <el-button type="danger" :loading="rollbackLoading" @click="executeNoRollback">执行回滚</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </div>

    <div class="card-wrapper">
      <h3 style="margin:0 0 16px;font-size:15px">回滚记录</h3>
      <el-table :data="rollbackLogs" v-loading="logsLoading" border stripe>
        <el-table-column prop="carrier_code" label="承运商" width="130" />
        <el-table-column prop="rollback_type" label="回滚类型" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ rollbackTypeLabel(row.rollback_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="rollback_count" label="回滚数量" width="100" align="center" />
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="rollbackStatusType(row.status)" size="small">
              {{ rollbackStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="operator" label="操作人" width="100" />
        <el-table-column prop="remark" label="原因" min-width="150" show-overflow-tooltip />
        <el-table-column prop="error_message" label="错误信息" width="150" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.error_message" style="color:#f56c6c">{{ row.error_message }}</span>
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column prop="finished_at" label="完成时间" width="160" />
      </el-table>

      <el-pagination
        v-if="logsTotal > 0"
        style="margin-top: 16px; justify-content: flex-end"
        background
        layout="total, sizes, prev, pager, next"
        :total="logsTotal"
        :page-size="logsPageSize"
        :current-page="logsPage"
        :page-sizes="[10, 20, 50]"
        @size-change="(s) => { logsPageSize = s; loadRollbackLogs() }"
        @current-change="(p) => { logsPage = p; loadRollbackLogs() }"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import request from '@/utils/request'

const rollbackTab = ref('time')
const rollbackLoading = ref(false)
const carrierOptions = ref([])
const logsLoading = ref(false)
const rollbackLogs = ref([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsPageSize = ref(20)

const timeFormRef = ref(null)
const noFormRef = ref(null)

const timeForm = reactive({
  carrier_code: '',
  start_time: '',
  end_time: '',
  operator: '',
  remark: '',
})

const noForm = reactive({
  tracking_no: '',
  carrier_code: '',
  operator: '',
  remark: '',
})

const timeRules = {
  carrier_code: [{ required: true, message: '请选择承运商', trigger: 'change' }],
  start_time: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  end_time: [{ required: true, message: '请选择结束时间', trigger: 'change' }],
}

const noRules = {
  tracking_no: [{ required: true, message: '请输入运单号', trigger: 'blur' }],
}

const rollbackTypeLabel = (type) => {
  const map = { time_range: '按时间', tracking_no: '按运单号', batch: '按批次' }
  return map[type] || type
}

const rollbackStatusLabel = (status) => {
  const map = { 0: '待执行', 1: '执行中', 2: '成功', 3: '失败' }
  return map[status] || '未知'
}

const rollbackStatusType = (status) => {
  const map = { 0: 'info', 1: 'warning', 2: 'success', 3: 'danger' }
  return map[status] || 'info'
}

const loadCarrierOptions = async () => {
  try {
    const res = await request.get('/carriers/select')
    carrierOptions.value = Array.isArray(res) ? res : (res.items || [])
  } catch (e) { /* ignore */ }
}

const loadRollbackLogs = async () => {
  logsLoading.value = true
  try {
    const res = await request.get('/tracking/rollback-logs', {
      params: { page: logsPage.value, page_size: logsPageSize.value },
    })
    rollbackLogs.value = res.items || []
    logsTotal.value = res.pagination ? res.pagination.total : (res.total || 0)
  } catch (e) { /* ignore */ }
  logsLoading.value = false
}

const executeTimeRollback = async () => {
  const valid = await timeFormRef.value.validate().catch(() => false)
  if (!valid) return

  try {
    await ElMessageBox.confirm(
      `确定回滚承运商「${timeForm.carrier_code}」在 ${timeForm.start_time} 至 ${timeForm.end_time} 的轨迹数据？此操作不可撤销。`,
      '确认轨迹回滚',
      { type: 'warning', confirmButtonText: '确认回滚', cancelButtonText: '取消' }
    )
  } catch (e) { return }

  rollbackLoading.value = true
  try {
    const res = await request.post('/tracking/rollback-time', timeForm)
    ElMessage.success(`轨迹回滚成功，共回滚 ${res.rollback_count || 0} 条记录`)
    loadRollbackLogs()
  } catch (e) {
    ElMessage.error('轨迹回滚失败')
  }
  rollbackLoading.value = false
}

const executeNoRollback = async () => {
  const valid = await noFormRef.value.validate().catch(() => false)
  if (!valid) return

  try {
    await ElMessageBox.confirm(
      `确定回滚运单号「${noForm.tracking_no}」的轨迹数据？此操作不可撤销。`,
      '确认轨迹回滚',
      { type: 'warning', confirmButtonText: '确认回滚', cancelButtonText: '取消' }
    )
  } catch (e) { return }

  rollbackLoading.value = true
  try {
    const res = await request.post('/tracking/rollback-no', noForm)
    ElMessage.success(`轨迹回滚成功，共回滚 ${res.rollback_count || 0} 条记录`)
    loadRollbackLogs()
  } catch (e) {
    ElMessage.error('轨迹回滚失败')
  }
  rollbackLoading.value = false
}

onMounted(() => {
  loadCarrierOptions()
  loadRollbackLogs()
})
</script>
