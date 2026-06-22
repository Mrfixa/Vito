import 'dart:convert';
import 'dart:io';
import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:ride_sharing_user_app/data/api_checker.dart';
import 'package:ride_sharing_user_app/data/api_client.dart';
import 'package:ride_sharing_user_app/features/auth/controllers/auth_controller.dart';
import 'package:ride_sharing_user_app/features/chat/domain/services/chat_service_interface.dart';
import 'package:ride_sharing_user_app/features/chat/screens/message_screen.dart';
import 'package:ride_sharing_user_app/features/chat/domain/models/channel_model.dart';
import 'package:ride_sharing_user_app/features/chat/domain/models/message_model.dart';
import 'package:ride_sharing_user_app/common_widgets/snackbar_widget.dart';
import 'package:ride_sharing_user_app/features/mart/screens/mart_driver_message_screen.dart';
import 'package:ride_sharing_user_app/features/splash/controllers/splash_controller.dart';
import 'package:ride_sharing_user_app/helper/file_validation_helper.dart';
import 'package:ride_sharing_user_app/helper/display_helper.dart';
import 'package:ride_sharing_user_app/helper/pusher_helper.dart';
import 'package:ride_sharing_user_app/util/app_constants.dart';

class ChatController extends GetxController implements GetxService{
  final ChatServiceInterface chatServiceInterface;

  ChatController({required this.chatServiceInterface});


  List <XFile>? _pickedImageFiles =[];
  List <XFile>? get pickedImageFile => _pickedImageFiles;
  FilePickerResult? _otherFile;
  FilePickerResult? get otherFile => _otherFile;
  File? _file;
  PlatformFile? objFile;
  File? get file=> _file;
  List<MultipartBody> _selectedImageList = [];
  List<MultipartBody> get selectedImageList => _selectedImageList;
  final List<dynamic> _conversationList=[];
  List<dynamic> get conversationList => _conversationList;
  final bool _paginationLoading = true;
  bool get paginationLoading => _paginationLoading;
  int? _messagePageSize;
  final int _messageOffset = 1;
  int? get messagePageSize => _messagePageSize;
  int? get messageOffset => _messageOffset;
  int? _pageSize;
  final int _offset = 1;
  bool isLoading = false;
  final String _name='';
  String get name => _name;
  final String _image='';
  String get image => _image;
  int? get pageSize => _pageSize;
  int? get offset => _offset;
  var conversationController = TextEditingController();
  final GlobalKey<FormState> conversationKey  = GlobalKey<FormState>();
  bool _isPickedImage = false;
  bool get isPickedImage => _isPickedImage;

  @override
  void onInit(){
    super.onInit();
    conversationController.text = '';
  }


  void pickMultipleImage(bool isRemove,{int? index}) async {
    if(isRemove) {
      if(index != null){
        _pickedImageFiles!.removeAt(index);
        _selectedImageList.removeAt(index);
      }
    }else {
      _isPickedImage = true;
      Future.delayed(const Duration(seconds: 1)).then((value) {
        update();
      });

      _pickedImageFiles = await FileValidationHelper.validateAndPickMultipleImages();
      if (_pickedImageFiles != null) {
        for(int i =0; i< _pickedImageFiles!.length; i++){
          _selectedImageList.add(MultipartBody('files[$i]',_pickedImageFiles![i]));
        }
      }
      _isPickedImage = false;
    }
    update();
  }


  void pickOtherFile(bool isRemove) async {
    if(isRemove){
      _otherFile=null;
      _file = null;
    }else{
      _otherFile = (await FilePicker.platform.pickFiles(
        type: FileType.custom,
        withReadStream: true,
        allowedExtensions: AppConstants.allowedImageExtensionsForFile,
      ))!;
      if (_otherFile != null) {
        if(await FileValidationHelper.validatePlatformFileSizeAsync(file: _otherFile!.files.single)){
          objFile = _otherFile!.files.single;
        }
      }
    }
    update();
  }

  void removeFile() async {
    _otherFile=null;
    update();
  }

  void cleanOldData(){
    _pickedImageFiles = [];
    _selectedImageList = [];
    _otherFile = null;
    _file = null;
  }

  ChannelModel? channelModel;

  Future<void> getChannelList(int offset) async{
    Response response = await chatServiceInterface.getChannelList(offset);
    if(response.statusCode == 200){
      if(offset == 1 ){
        channelModel = ChannelModel.fromJson(response.body);
      }else{
        channelModel!.totalSize =  ChannelModel.fromJson(response.body).totalSize;
        channelModel!.offset =  ChannelModel.fromJson(response.body).offset;
        channelModel!.data!.addAll(ChannelModel.fromJson(response.body).data!);
      }
      isLoading = false;
    }else{
      ApiChecker.checkApi(response);
    }
    update();
  }


  Future<void> createChannel(String userId, {required tripId}) async{
    isLoading = true;
    update();
    try {
      Response response = await chatServiceInterface.createChannel(userId,tripId);
      if(response.statusCode == 200){
        final data = response.body is Map ? response.body['data'] : null;
        final channel = data is Map ? data['channel'] : null;
        final channelId = channel is Map ? channel['id']?.toString() : null;
        if (channelId == null) {
          showCustomSnackBar('something_went_wrong'.tr);
        } else {
          final user = data['user'] is Map ? data['user'] : {};
          final userName = '${user['first_name'] ?? ''} ${user['last_name'] ?? ''}'.trim();
          Get.to(()=> MessageScreen(channelId : channelId, tripId: channel['trip_id']?.toString() ?? tripId, userName: userName));
        }
      }else{
        ApiChecker.checkApi(response);
      }
    } catch (e) {
      showCustomSnackBar('something_went_wrong'.tr);
    } finally {
      isLoading = false;
      update();
    }
  }

  Future<void> createMartChannel(String customerId, String orderId, String customerName) async {
    isLoading = true;
    update();
    try {
      Response response = await chatServiceInterface.createMartChannel(customerId, orderId);
      if (response.statusCode == 200) {
        final data = response.body is Map ? response.body['data'] : null;
        final channel = data is Map ? data['channel'] : null;
        final channelId = channel is Map ? channel['id']?.toString() : null;
        if (channelId == null) {
          showCustomSnackBar('something_went_wrong'.tr);
        } else {
          final oId = channel['trip_id']?.toString() ?? orderId;
          Get.to(() => MartDriverMessageScreen(channelId: channelId, orderId: oId, userName: customerName));
        }
      } else {
        ApiChecker.checkApi(response);
      }
    } catch (e) {
      showCustomSnackBar('something_went_wrong'.tr);
    } finally {
      isLoading = false;
      update();
    }
  }



  MessageModel? messageModel;
  Future<Response> getConversation(String channelId, int offset) async{
    isLoading = true;
    Response response = await chatServiceInterface.getConversation(channelId, offset);
    if(response.statusCode == 200){
      if(offset == 1 ){
        messageModel = MessageModel.fromJson(response.body);

      }else{
        messageModel!.totalSize =  MessageModel.fromJson(response.body).totalSize;
        messageModel!.offset =  MessageModel.fromJson(response.body).offset;
        messageModel!.data!.addAll(MessageModel.fromJson(response.body).data!);
      }
      isLoading = false;
    }else{
      isLoading = false;
      ApiChecker.checkApi(response);
    }
    update();
    return response;
  }


  bool isSending = false;
  Future<void> sendMessage(String channelId, String tripId) async{
    isSending = true;
    update();
    Response response = await chatServiceInterface.sendMessage(conversationController.value.text, channelId , tripId,_selectedImageList, objFile);
    if(response.statusCode == 200){
      isSending = false;
      getConversation(channelId, 1);
      conversationController.text='';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
    }
    else if(response.statusCode == 400){
      isSending = false;
      final errors = response.body['errors'];
      String message = (errors is List && errors.isNotEmpty && errors[0] is Map && errors[0]['message'] != null)
          ? errors[0]['message'].toString()
          : 'failed_to_upload';
      if(message.contains("png  jpg  jpeg  csv  txt  xlx  xls  pdf")){
        message = "the_files_types_must_be";
      }
      if(message.contains("failed to upload")){
        message = "failed_to_upload";
      }
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
      snackBarWidget(message.tr);
    }
    else{
      isSending = false;
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile=null;
      objFile =null;
      _file=null;
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
  }

  PrivateChannel? _rideChannel;
  PrivateChannel? _martChannel;

  @override
  void onClose() {
    try {
      _rideChannel?.unsubscribe();
    } catch (e) { debugPrint('Chat error: $e'); }
    try {
      _martChannel?.unsubscribe();
    } catch (e) { debugPrint('Chat error: $e'); }
    super.onClose();
  }

  late PrivateChannel channel;

  String id ="";

  void subscribeMessageChannel(String tripId){
    id = "";
    if(id == ""){
      id = tripId;
    }

    if (Get.find<SplashController>().pusherConnectionStatus != null && Get.find<SplashController>().pusherConnectionStatus == 'Connected'){
      // Unsubscribe previous ride channel if any
      try { _rideChannel?.unsubscribe(); } catch (e) { debugPrint('Chat error: $e'); }
      channel = PusherHelper.pusherClient!.privateChannel("private-driver-ride-chat.$id", authorizationDelegate:
      EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
        authorizationEndpoint: Uri.parse('https://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
        headers:  {
          "Accept": "application/json",
          "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
          "Access-Control-Allow-Origin": "*",
          'Access-Control-Allow-Methods':"PUT, GET, POST, DELETE, OPTIONS"
        },
      ));
      if(channel.currentStatus == null){
        channel.subscribe();
        _rideChannel = channel;

        channel.bind("driver-ride-chat.$id").listen((event) {
          try {
            final decoded = jsonDecode(event.data!);
            final conv = decoded['channel_conversation'];
            if (conv != null && id == conv['channel']?['trip_id']) {
              if (messageModel?.data != null) {
                messageModel!.data!.insert(0, Message.fromJson(conv));
                update();
              }
            }
          } catch (e) {
            debugPrint('Ride chat event error: $e');
          }
        });
      } else {
        _rideChannel = channel;
      }
    }

  }



  late PrivateChannel martChannel;

  void subscribeMartMessageChannel(String orderId) {
    id = orderId;
    if (Get.find<SplashController>().pusherConnectionStatus != null &&
        Get.find<SplashController>().pusherConnectionStatus == 'Connected') {
      // Unsubscribe previous mart channel if any
      try { _martChannel?.unsubscribe(); } catch (e) { debugPrint('Chat error: $e'); }
      martChannel = PusherHelper.pusherClient!.privateChannel(
          "private-driver-mart-chat.$orderId",
          authorizationDelegate: EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
            authorizationEndpoint: Uri.parse(
                'https://${Get.find<SplashController>().config!.webSocketUrl}/broadcasting/auth'),
            headers: {
              "Accept": "application/json",
              "Authorization": "Bearer ${Get.find<AuthController>().getUserToken()}",
              "Access-Control-Allow-Origin": "*",
              'Access-Control-Allow-Methods': "PUT, GET, POST, DELETE, OPTIONS"
            },
          ));
      if (martChannel.currentStatus == null) {
        martChannel.subscribe();
        _martChannel = martChannel;
        martChannel.bind("driver-mart-chat.$orderId").listen((event) {
          try {
            final data = jsonDecode(event.data!);
            final eventOrderId = data['order_id'] ?? data['channel_conversation']?['channel']?['trip_id'];
            final conv = data['channel_conversation'];
            if (eventOrderId == orderId && conv != null && messageModel?.data != null) {
              messageModel!.data!.insert(0, Message.fromJson(conv));
              update();
            }
          } catch (e) {
            debugPrint('Mart chat event error: $e');
          }
        });
      } else {
        _martChannel = martChannel;
      }
    }
  }

  Future<void> sendMartMessage(String channelId, String orderId) async {
    isSending = true;
    update();
    Response response = await chatServiceInterface.sendMartMessage(
        conversationController.value.text, channelId, orderId, _selectedImageList, objFile);
    if (response.statusCode == 200) {
      isSending = false;
      getConversation(channelId, 1);
      conversationController.text = '';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
    } else if (response.statusCode == 400) {
      isSending = false;
      final errors = response.body['errors'];
      String message = (errors is List && errors.isNotEmpty && errors[0] is Map && errors[0]['message'] != null)
          ? errors[0]['message'].toString()
          : 'something_went_wrong';
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
      snackBarWidget(message.tr);
    } else {
      isSending = false;
      _pickedImageFiles = [];
      _selectedImageList = [];
      _otherFile = null;
      objFile = null;
      _file = null;
      ApiChecker.checkApi(response);
    }
    isLoading = false;
    update();
  }

  bool _channelRideStatus = true;
  bool get channelRideStatus => _channelRideStatus;
  void findChannelRideStatus(String channelId) async{
    Response response = await chatServiceInterface.findChannelRideStatus(channelId);
    if(response.statusCode == 200 && response.body['data'] != null) {
      if(response.body['data'] == "cancelled" || response.body['data'] == 'completed'){
        _channelRideStatus = false;
      }else{
        _channelRideStatus = true;
      }
    } else {
      _channelRideStatus = true; // default to enabled if check fails
    }
    update();
  }



}