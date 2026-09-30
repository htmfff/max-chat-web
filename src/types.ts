export interface Credentials {
  idInstance: string;
  apiTokenInstance: string;
}

export type MessageDirection = 'in' | 'out';

export type MessageStatus = 'sending' | 'sent' | 'failed';

export interface Message {
  id: string;
  chatId: string;
  text: string;
  direction: MessageDirection;
  timestamp: number;
  status: MessageStatus;
}

export interface Chat {
  chatId: string;
  phone: string;
  title: string;
  preview?: string;
  updatedAt: number;
  unread: number;
}

export interface WebhookSenderData {
  chatId: string;
  chatName?: string;
  chatType?: string;
  senderName?: string;
  senderPhoneNumber?: number | string;
}

export interface WebhookMessageData {
  typeMessage?: string;
  idMessage?: string;
  status?: string;
  textMessageData?: {
    textMessage?: string;
    /** Разные версии API отдавали поле с таким именем — поддерживаем обе. */
    text?: string;
  };
}

export interface WebhookBody {
  typeWebhook: string;
  /** Unix-время в секундах. */
  timestamp?: number;
  idMessage?: string;
  senderData?: WebhookSenderData;
  messageData?: WebhookMessageData;
}

export interface NotificationResponse {
  receiptId?: number;
  body?: WebhookBody | null;
}

export interface CheckAccountResponse {
  exist?: boolean;
  chatId?: string;
  name?: string;
  contactName?: string;
}

export interface InstanceSettings {
  idInstance: number;
  stateInstance?: string;
  typeInstance?: string;
}

export interface InstanceState {
  stateInstance: string;
}